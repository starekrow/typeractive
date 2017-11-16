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

		$pdate = date( "D M d, Y", $date );
		$dl = $post->GetDateline();
		if ($dl !== "") {
			$dl = "$pdate - $dl";
		} else {
			$dl = $pdate;
		}
		
		$toks->dateline = $dl;
		$toks->mainpost = $md->text( $text );
		$toks->bio = $md->text( $blog->GetBiography() );
		$toks->header = $blog->GetHeader();
		$toks->title = $post->GetTitle();
		return new Dict( [
			 "tokens" => $toks
			,"html" => file_get_contents( "res/blog_viewpost.html" )
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
		if (empty($_SESSION['userid'])) {
			return $this->NeedToSignIn();
		}
		$this->html = file_get_contents( "res/dashboard.html" );
		$this->location = Http::$appRootUrl . "/-/dashboard";
	}
}
