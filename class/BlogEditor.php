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
	GetBlog
	=====================
	*/
	function GetBlog()
	{
		$u = UserData::Load( $_SESSION['userid'] );
		$b = $u->GetBlog();
		if (!$b) {
			$b = BlogData::Create( $u->id );
			$b->Save();
		}
		return $b;
	}


	/*
	=====================
	ConfigureBlog
	=====================
	*/
	function ConfigureBlog()
	{
		if ($this->method != "POST") {
			$this->html = file_get_contents( "res/blog_configure.html" );
			$b = $this->GetBlog();
			$this->tokens->title = $b->GetTitle();
			$this->tokens->header = $b->GetHeader();
			$this->tokens->bio = $b->GetBiography();
			$this->tokens->rootpost = $b->GetDefaultPost();
			return;
		}
		$args = $this->args;
		$err = null;
		if ($args->title === null || $args->header === null) {
			$err = "Invalid parameters";
		} else {
			$b = $this->GetBlog();
			$b->SetTitle( $args->title );
			$b->SetHeader( $args->header );
			$b->SetBiography( $args->bio );
			$b->SetDefaultPost( $args->rootpost );
			$b->Save();
		}
		if ($err) {
			$this->ReplyJson( [
				"alert" => "Error during update: $err."
			] );
		} else {
			$this->ReplyJson( [
				"alert" => "Update successful."
				,"goto" => "-/dashboard"
			] );
		}
	}


	/*
	=====================
	NewDraft
	=====================
	*/
	function NewDraft()
	{
		if ($this->method != "POST") {
			$this->html = file_get_contents( "res/blog_draft.html" );
			$b = $this->GetBlog();
			$this->tokens->screen_title = "New Post";
			$this->tokens->title = "";
			$this->tokens->text = "";
			$this->tokens->dateline = "";
			return;
		}
		$args = $this->args;
		$err = null;
		if ($args->title === null || $args->text === null) {
			$err = "Invalid parameters";
		} else {
			$b = $this->GetBlog();
			$b->CreateDraft( [
				 "title" => $args->title
				,"text" => $args->text
			] );
		}
		if ($err) {
			$this->ReplyJson( [
				"alert" => "Error during update: $err."
			] );
		} else {
			$this->ReplyJson( [
				"alert" => "Created new draft."
				,"goto" => "-/dashboard"
			] );
		}
	}


	/*
	=====================
	EditPost
	=====================
	*/
	function EditPost()
	{
		$b = $this->GetBlog();
		$p = PostData::Load( $this->args->post );
		if (!$p || $p->GetAuthor() != $b->GetAuthor()) {
			$this->html = "Invalid post ID";
			return;
		}

		if ($this->method != "POST") {
			$this->html = file_get_contents( "res/blog_draft.html" );
			$this->tokens->screen_title = "Edit Post";
			$this->tokens->title = $p->GetTitle();
			$this->tokens->text = $p->GetDraft();
			$this->tokens->dateline = $p->GetDateline();
			$this->tokens->postid = $this->args->post;
			return;
		}
		$args = $this->args;
		$err = null;
		$p->SetDraft( $args->text );
		$p->SetTitle( $args->title );
		$p->SetDateline( $args->dateline );
		$p->Save();

		$this->html = "OK";
		return;
		if ($args->title === null || $args->text === null) {
			$err = "Invalid parameters";
		} else {
			$b = $this->GetBlog();
			$b->CreateDraft( [
				 "title" => $args->title
				,"text" => $args->text
			] );
		}
		if ($err) {
			$this->ReplyJson( [
				"alert" => "Error during update: $err."
			] );
		} else {
			$this->ReplyJson( [
				"alert" => "Created new draft."
				,"goto" => "-/dashboard"
			] );
		}
	}

	/*
	=====================
	PublishPost
	=====================
	*/
	function PublishPost()
	{
		$b = $this->GetBlog();
		$p = PostData::Load( $this->args->post );
		if (!$p || $p->GetAuthor() != $b->GetAuthor()) {
			$this->html = "Invalid post ID";
			return;
		}
		if ($this->method != "POST") {
			$this->html = "Invalid method";
			return;
		}
		$ntext = $p->GetDraft();
		$otext = $p->GetText();
		$err = null;
		if ($ntext == "") {
			$err = "Empty draft";
		}
		if ($otext === $ptext) {
			$err = "No changes in draft";
		}
		if ($err) {
			$this->html = $err;
			return;
		}
		$p->SetText( $ntext );
		$p->SetState( "published" );
		$p->Save();
		// TODO: linking
	}

	/*
	=====================
	PreviewPost
	=====================
	*/
	function PreviewPost()
	{
		$b = $this->GetBlog();
		$p = PostData::Load( $this->args->post );
		if (!$p || $p->GetAuthor() != $b->GetAuthor()) {
			$this->html = "Invalid post ID";
			return;
		}
		$got = BlogServer::RenderPost( $b, $p, [ "draft" => true ] );
		$this->html = $got->html;
		foreach ($got->tokens as $k => $v ) {
			$this->tokens->$k = $v;
		}
	}

	/*
	=====================
	ListPosts
	=====================
	*/
	function ListPosts()
	{
		$b = $this->GetBlog();
		$dl = $b->ListPosts( "draft" );
		$pl = $b->ListPosts( "published" );
		$pt = file_get_contents( "res/blog_postlist_entry.html" );

		$odl = [];
		foreach ($dl as $el) {
			$title = $el->GetTitle();
			if ($title === "") {
				$title = "(untitled)";
			}
			$odl[] = $this->ReplaceAngTokens( $pt, [
				 "title" => $title
				,"postid" => $el->id
				,"mtime" => date("Y-m-d H:i:s", $el->GetDraftTimestamp() )
			] );
		}

		$opl = [];
		foreach ($pl as $el) {
			$opl[] = $this->ReplaceAngTokens( $pt, [
				 "title" => $title
				,"postid" => $el->id
				,"mtime" => date("Y-m-d H:i:s", $el->GetLastUpdatedTimestamp() )
			] );
		}

		$this->tokens->draftlist = implode( "", $odl );
		$this->tokens->postlist = implode( "", $opl );
		if (!count( $opl )) {
			$this->tokens->postlist = "<tr><td colspan=2 class=noentries>No Entries";
		}
		$this->html = file_get_contents( "res/blog_postlist.html" );
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
		case "draft":
			return $this->NewDraft();
		case "edit":
			return $this->EditPost();
		case "save":
			return $this->SavePost();
		case "publish":
			return $this->PublishPost();
		case "views":
			return $this->ShowViews();
		case "posts":
			return $this->ListPosts();
		case "drafts":
			return $this->ListDrafts();
		case "preview":
			return $this->PreviewPost();
		default:
			Http::NotFound();
			$this->html = "Unknown editor link";
		}
	}
}
