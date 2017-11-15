<?php /* Copyright (C) 2017 David O'Riva. MIT License.
       ********************************************************/
namespace Typeractive;

/*
================================================================================

BlogEditor

Creates drafts, edits pages and works with the history

================================================================================
*/

class BlogEditor extends PageServer
{
	/*
	=====================
	NeedToSignIn
	=====================
	*/
	function NeedToSignIn()
	{
		$link = '<a href="#signin" onclick="login_start();return false;">';
		$endlink = '</a>';
		$this->html = [
			 "<div style='margin-top:100px'>"
			,"You must {$link}sign in{$endlink} to use the editor."
			,"</div>"
		];
	}

	/*
	=====================
	ConfigureBlog
	=====================
	*/
	function ConfigureBlog()
	{
		$this->html = file_get_contents( "res/blog_configure.html" );
		$u = UserData::Load( $_SESSION['userid'] );
		$b = $u->GetBlog();
		if (!$b) {
			//$b = $u->AddBlog();
		}
	}


	/*
	=====================
	NewPost
	=====================
	*/
	function NewPost()
	{
	}


	/*
	=====================
	GetPage
	=====================
	*/
	function GetPage()
	{
		// Nothing the editor does is cacheable
		Http::StopClientCache();
		if (empty($_SESSION['userid'])) {
			return $this->NeedToSignIn();
		}
		switch ($this->path) {
		case "configure":
			return $this->ConfigureBlog();
		case "new":
			return $this->NewPost();
		case "save":
			return $this->SavePost();
		case "publish":
			return $this->PublishPost();
		case "views":
			return $this->ListViews();
		case "posts":
			return $this->ListPosts();
		case "drafts":
			return $this->ListDrafts();
		default:
			Http::NotFound();
			$this->html = "Unknown editor link";
		}
	}
}
