<?php /* Copyright (C) 2017 David O'Riva. MIT License.
       ********************************************************/
namespace Typeractive;

/*
================================================================================

BlogServer

Serves blog pages and wrangles comments (with the help of the CommentServer)

================================================================================
*/

class BlogServer extends PageServer
{

	/*
	=====================
	GetToc
	=====================
	*/
	static function GetToc( $blog )
	{
		$pl = $blog->ListPosts( "published" );
		$pt = file_get_contents( "res/blog_viewposttocline.html" );
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
			$opl[] = self::ReplaceAngTokens( $pt, [
				 "title" => $title
				,"link" => htmlspecialchars( $elink )
				,"date" => date("M j", $el->GetPostTimestamp() )
			] );
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
		$toks->mainpost = $md->text( $text );
		$toks->bio = $md->text( $blog->GetBiography() );
		$toks->header = $blog->GetHeader();
		$toks->title = $post->GetTitle();
		$toks->toc = implode( "", self::GetToc( $blog ) );
		return new Dict( [
			 "tokens" => $toks
			,"html" => file_get_contents( "res/blog_viewpost.html" )
			,"author" => $post->getAuthor()
			,"poststate" => $post->GetState()
		] );
	}

	/*
	=====================
	GetPage
	=====================
	*/
	function GetPage()
	{
		Http::StopClientCache();
		$id = $this->request->id;

		$key = "full_post_" . $id;
		if (!Cache::lock( $key, 0, $val, 30, 10 )) {
			$val = $val ? $val : Cache::wait( $key, 10 );
			if (!$val) {
				$this->html = "Error retrieving post. Please try again.";
				return;
			}
		} else {
			$post = PostData::Load( $id );
			$blog = BlogData::Load( $post->GetBlogId() );
			$val = self::RenderPost( $blog, $post );
			Cache::set( $key, $val, 300 );		// 5 minutes
			Cache::unlock( $key );
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
