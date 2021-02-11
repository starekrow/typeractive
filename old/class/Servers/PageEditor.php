<?php /* Copyright (C) 2017 David O'Riva. MIT License.
       ********************************************************/
namespace Typeractive;

/*
================================================================================

BlogEditor

Creates drafts, edits pages and works with the history

================================================================================
*/

class PageEditor extends HtmlServer
{
	public $blog;

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
	GetUser
	=====================
	*/
	function GetUser()
	{
		if ($this->user) {
			return $this->user;
		}
		$u = UserData::Load( $_SESSION['userid'] );
		if (!$u) {
			throw new AppError( "UnknownUser" );
		}
		$this->user = $u;
		return $u;
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
		$this->replyType = "json";
		$args = $this->args;
		$err = null;
		if ($args->title === null || $args->header === null) {
			throw new AppError( "BadParameter", "missing title or header" );
		}
		$b = $this->GetBlog();
		$b->SetTitle( $args->title );
		$b->SetHeader( $args->header );
		$b->SetBiography( $args->bio );
		$b->SetDefaultPost( $args->rootpost );
		$b->Save();
		$this->ReplyJson( [
			"alert" => "Update successful."
			,"goto" => "-/dashboard"
		] );
	}


	/*
	=====================
	SetupLink
	=====================
	*/
	function SetupLink( $post, $link )
	{
		$lid = $post->GetLinkId();
		$prefix = "/" . $_SESSION['username'] . "/";
		$path = $link;
		if (substr( $link, 0, strlen( $prefix ) ) !== $prefix) {
			$path = $prefix . $link;
		}
		if ($link === "" || $link === null) {
			if ($lid) {
				LinkData::Remove( $lid );
				$post->SetLinkId( null );
				$post->Save();
			}
			return null;
		}
		if ($post->GetState() == "published") {
			$lt = "blogpost";
		} else {
			$lt = "draft";
		}
		if ($lid) {
			$l = LinkData::Load( $lid );
			if (!$l) {
				return false;
			}
			if (!$l->ChangeLink( $path )) {
				return false;
			}
			if ($l->GetType() != $lt) {
				$l->SetType( $lt );
				$l->Save();
			}
			return $l;
		}
		$l = LinkData::Register( $path, $lt, $post->id );
		if (!$l) {
			return false;
		}
		$post->SetLinkId( $l->id );
		$post->Save();
		return $l;
	}


	/*
	=====================
	NewLink
	=====================
	*/
	function NewLink()
	{
		$u = $this->GetUser();
		$this->html = file_get_contents( "res/link_edit.html" );
		$this->tokens->screen_title = "New Link";
		$this->tokens->path = "";
		$this->tokens->target = "";
		$this->tokens->linkid = "new";
	}


	/*
	=====================
	EditLink
	=====================
	*/
	function EditLink()
	{
		$u = $this->GetUser();

		$l = LinkData::Load( $this->args->link );
		if (!$l || $l->GetOwner() != $u->id || $l->GetType() != "page") {
			throw new AppError( "BadParameter", "Invalid link ID" );
		}
		$this->html = file_get_contents( "res/link_edit.html" );
		$this->tokens->screen_title = "Edit Page";
		$this->tokens->path = $l->GetLink();
		$this->tokens->target = $l->GetReference();
		$this->tokens->linkid = $this->args->link;
	}

	/*
	=====================
	SaveLink
	=====================
	*/
	function SaveLink()
	{
		$u = $this->GetUser();
		$this->replyType = "json";
		$args = $this->args;
		if ($this->method != "POST") {
			throw new AppError( "NotAllowed", "Invalid method" );
		}
		if ($args->path === null || !is_numeric( $args->target )) {
			throw new AppError( "BadParameter" );
		}
		$got = PageData::Load( $args->target );
		if (!$got || $got->GetOwner() != $u->id) {
			throw new AppError( "BadParameter","Invalid page ID" );
		}
		if ($args->link == "new") {
			$link = LinkData::Register( $args->path, "page", $args->target );
			if (!$link) {
				throw new AppError( "InUse", "That path is already in use" );
			}
			Cache::remove( "link:" . $args->path );
			$link->SetOwner( $u->id );
			$link->Save();
			$this->ReplyJson( [
				 "alert" => "Created new link."
				,"run" => 
					"assign_link_id(\"" . $page->id . "\");" .
					"unmask_link_tools();"
			] );
			return;
		}
		$l = LinkData::Load( $this->args->link );
		if (!$l || $l->GetOwner() != $u->id || $l->GetType() != "page") {
			throw new AppError( "NotFound", "Invalid link ID" );
		}

		Cache::remove( "link:" . $l->GetLink() );
		$err = null;
		if (!$l->ChangeLink( $args->path )) {
			throw new AppError( "InUse", "That path is already in use" );
		}
		Cache::remove( "link:" . $args->path );
		$l->SetReference( $args->target );
		$l->Save();

		$this->ReplyJson( [
			 "alert" => "Saved."
			,"run" => "unmask_link_tools();"
		] );
	}



	/*
	=====================
	NewPage
	=====================
	*/
	function NewPage()
	{
		$u = $this->GetUser();
		$this->html = file_get_contents( "res/page_edit.html" );
		$this->tokens->screen_title = "New Page";
		$this->tokens->name = "";
		$this->tokens->body = "";
		$this->tokens->pageid = "new";
	}


	/*
	=====================
	EditPage
	=====================
	*/
	function EditPage()
	{
		$u = $this->GetUser();

		$p = PageData::Load( $this->args->page );
		if (!$p || $p->GetOwner() != $u->id) {
			$this->html = "Invalid page ID";
			return;
		}
		$this->html = file_get_contents( "res/page_edit.html" );
		$this->tokens->screen_title = "Edit Page";
		$this->tokens->name = $p->GetName();
		$this->tokens->body = $p->GetBody();
		$this->tokens->pageid = $this->args->page;
	}

	/*
	=====================
	SavePage
	=====================
	*/
	function SavePage()
	{
		$u = $this->GetUser();
		$this->replyType = "json";
		$args = $this->args;
		if ($this->method != "POST") {
			throw new AppError( "NotAllowed", "Invalid method" );
		}
		if ($args->page == "new") {
			if ($args->name === null || $args->body === null) {
				throw new AppError( "BadParameter" );
			} else {
				$page = PageData::Create( $u->id, $args->name );
				$page->SetBody( $args->body );
				$page->Save();
				$this->ReplyJson( [
					 "alert" => "Created new page."
					,"run" => 
						"assign_page_id(\"" . $page->id . "\");" .
						"unmask_page_tools();"
				] );
			}
			return;
		}
		$p = PageData::Load( $args->page );
		if (!$p || $p->GetOwner() != $u->id) {
			throw new AppError( "NotFound", "Invalid page ID" );
		}
		Cache::remove( "full_page_" . $p->id );

		$err = null;
		$p->SetName( $args->name );
		$p->SetBody( $args->body );
		$p->Save();

		$this->ReplyJson( [
			 "alert" => "Saved."
			,"run" => "unmask_page_tools();"
		] );
	}


	/*
	=====================
	PreviewPage
	=====================
	*/
	function PreviewPage()
	{
		$u = $this->GetUser();
		$p = PageData::Load( $this->args->page );
		if (!$p || $p->GetOwner() != $u->id) {
			throw new AppError( "NotFound", "Invalid post ID" );
		}
		$got = PageServer::RenderPage( $p, [ "preview" => true ] );
		if (isset( $got->raw )) {
			$this->tokens->Merge( $got->tokens );
			$t = $this->ReplaceAngTokens( $got->raw, $this->tokens );
			$this->ReplyRaw( $t );
		} else {
			$this->html = $got->html;
			$this->tokens->Merge( $got->tokens );
			$this->headblock = $got->headblock;			
		}
	}

	/*
	=====================
	ListPages
	=====================
	*/
	function ListPages()
	{
		$u = $this->GetUser();
		$lst = PageData::ListPages( $u->id );
		$pl = [];
		foreach ($lst as $el) {
			$nm = $el->GetName();
			if ($nm === null || $nm === "") {
				$nm = "(Unnamed)";
			}
			$pl[] = [
				 "pageid" => $el->id
				,"title" => $nm
				,"mtime" => $el->GetUpdatedTimestamp()
			];
		}
		$this->tokens->pagelist = $pl;

		$lst = LinkData::Find( [ "type" => "page", "ownerid" => $u->id ] );
		$ll = [];
		foreach ($lst as $el) {
			$ll[] = [
				 "path" => $el->GetLink()
				,"linkid" => $el->id
				,"pageid" => $el->GetReference()
			];
		}
		$this->tokens->linklist = $ll;
		$this->html = file_get_contents( "res/page_list.html" );
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
		try {
			$p = $this->path != "" ? substr( $this->path, 1 ) : "";
			switch ($p) {
			case "new":
				return $this->NewPage();
			case "edit":
				return $this->EditPage();
			case "save":
				return $this->SavePage();
			case "preview":
				return $this->PreviewPage();
			case "newlink":
				return $this->NewLink();
			case "editlink":
				return $this->EditLink();
			case "savelink":
				return $this->SaveLink();
			case "views":
				return $this->ShowViews();
			case "list":
				return $this->ListPages();
			default:
				throw new AppError( "NotFound", "Unknown editor link" );
			}
		} catch (AppError $e) {
			if ($this->replyType == "json") {
				$this->ReplyJson( [
					"alert" => $e->userMessage()
				] );
			} else {
				if ($e->getCode() == "NotFound") {
					Http::NotFound();
				}
				$this->html = $e->userMessage();
			}
		}
	}
}
