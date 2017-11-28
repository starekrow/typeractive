<?php /* Copyright (C) 2017 David O'Riva. MIT License.
       ********************************************************/
namespace Typeractive;

/*
================================================================================

BlogServer

Serves blog pages and wrangles comments (with the help of the CommentServer)

================================================================================
*/

class BlogServer extends HtmlServer
{

	/*
	=====================
	GetToc
	=====================
	*/
	static function GetToc( $blog )
	{
		$pl = $blog->ListPosts( "published" );
		//$pt = file_get_contents( "res/blog_viewposttocline.html" );
		$opl = [];
		foreach ($pl as $el) {
			$title = $el->GetTitle();
			if ($title === "") {
				$title = "(untitled)";
			}
			$lid = $el->GetLinkId();
			if (!$lid) {
				continue;
			}
			$link = LinkData::Load( $lid );
			$elink = substr( $link->GetLink(), 1 );
			$opl[] = [
				 "title" => $title
				,"link" => htmlspecialchars( $elink )
				,"id" => $el->id
				,"date" => date("M j", $el->GetPostTimestamp() )
				,"fullpost" => $el
			];
		}
		return $opl;
	}		


	/*
	=====================
	RenderPost
	=====================
	*/
	static function RenderPost( $blog, $post, $options = null )
	{
		$options = new Dict( $options );
		if ($options->draft) {
			$text = $post->GetDraft();
			$date = $post->GetDraftTimestamp();
		} else {
			$text = $post->GetText();
			$date = $post->GetPostTimestamp();
		}
		$md = new \Parsedown();
		$toks = new Dict();

		$pdate = date( "l M jS, Y", $date );
		$dl = $post->GetDateline();
		if ($dl !== "") {
			$dl = "$pdate - $dl";
		} else {
			$dl = $pdate;
		}
		
		$toks->postid = $post->id;
		$toks->dateline = $dl;
		$text = $md->text( $text );
		$toks->bio = $md->text( $blog->GetBiography() );
		$toks->header = $blog->GetHeader();
		$toks->title = $post->GetTitle();
		$toks->toclist = self::GetToc( $blog );
		$toks->mainpost = self::ReplaceAngTokens( $text, $toks );
		return new Dict( [
			 "tokens" => $toks
			,"html" => file_get_contents( "res/blog_viewpost.html" )
			,"author" => $post->getAuthor()
			,"poststate" => $post->GetState()
		] );
	}

	/*
	=====================
	MainPage

	Render a summary of the latest few posts
	=====================
	*/
	function MainPage()
	{
		$id = $this->request->id;
		$blog = BlogData::Load( $id );
		$toc = self::GetToc( $blog );
		$tocl = array_slice( $toc, 0, 5 );
		$md = new \Parsedown();

		$toks = new Dict();
		$teasers = [];

		foreach ($tocl as $el) {
			$post = $el["fullpost"];

			$text = $post->GetText();
			$text = explode( "\n", $text );
			$text = implode( "", array_slice( $text, 0, 20 ) );

			$date = $post->GetPostTimestamp();
			$toks = new Dict();

			$pdate = date( "l M jS, Y", $date );
			$dl = $post->GetDateline();
			if ($dl !== "") {
				$dl = "$pdate - $dl";
			} else {
				$dl = $pdate;
			}

			$block = new Dict();
			$block->postid = $post->id;
			$block->postlink = $el["link"];
			$block->dateline = $dl;
			$block->title = $post->GetTitle();
			$text = $this->ReplaceAngTokens( $md->text( $text ), $block );
			$block->mainpost = $text;

			$teasers[] = $block;
		}
		$toks->teasers = $teasers;
		$toks->toclist = $toc;

		$toks->bio = $md->text( $blog->GetBiography() );
		$toks->header = $blog->GetHeader();
//		$toks->toclist = self::GetToc( $blog );

		$this->tokens->Merge( $toks );
		$this->html = file_get_contents( "res/blog_frontpage.html" );

	}

	/*
	=====================
	GetPage
	=====================
	*/
	function GetPage()
	{
		if ($this->request->type == "blogmain") {
			return $this->MainPage();
		}
		Http::StopClientCache();
		$id = $this->request->id;
		$key = "full_post_" . $id;
		if (isset( $this->args->nocache ) && !empty( $_SESSION['userid'] )) {
			$key = null;
		}
		if ($key && !Cache::lock( $key, 0, $val, 30, 10 )) {
			$val = $val ? $val : Cache::wait( $key, 10 );
			if (!$val) {
				$this->html = "Error retrieving post. Please try again.";
				return;
			}
		} else {
			$post = PostData::Load( $id );
			$blog = BlogData::Load( $post->GetBlogId() );
			$val = self::RenderPost( $blog, $post );
			if ($key) {
				Cache::set( $key, $val, 300 );		// 5 minutes
				Cache::unlock( $key );
			}
		}
		$got = new Dict( $val );
		$this->tokens->Merge( $got->tokens );
		$this->html = $got->html;

		if (isset($_SESSION['userid']) && 
			$_SESSION['userid'] == $got->author
		   ) {
			$this->tokens->show_post_tools = "show";
		   	if ($got->poststate == "published") {
		   		$this->tokens->show_post_tools .= " published";
		   	}
		}
	}
}
