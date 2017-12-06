<?php /* Copyright (C) 2017 David O'Riva. MIT License.
       ********************************************************/
namespace Typeractive;

/*
================================================================================

CommentServer

Creates drafts, edits pages and works with the history

================================================================================
*/

class CommentServer extends AjaxServer
{
	const COMMENTS_PER_PAGE = 50;

	/*
	=====================
	ShowError
	=====================
	*/
	public function ShowError( $str )
	{
		return [
			"run" => 'login_error(' .
					json_encode( $str ) .
				");"
		];
	}


	/*
	=====================
	PrepGuestText

	Comments by guests (or unverified users) only support a limited subset of 
	markdown. Pretty much just:

	Paragraph lines are folded as expected, with 
	*italic* and **bold** text.	Other text styles 
	include `code`, ~~strikethrough~~, 
	^^superscript^^, ,,subscript,,.

	Links are not allowed at all. Things that look like 
	links will be reformatted as "hxxp host path?query". 

	Control characters except TAB will be removed.

	Entities are supported, except any unrecognized entities will be rewritten
	to expose them. No raw tags are allowed at all.

	    Preformatted text block

	* bullets
	  with indenting
	* and more bullets

	> blockquotes

	=====================
	*/
	public function PrepGuestText( $text )
	{
	}

	/*
	=====================
	cmd_add

	Adds a new comment to a post
	=====================
	*/
	public function cmd_add( $path )
	{

	}



	/*
	=====================
	cmd_load
	=====================
	*/
	public function cmd_load( $path )
	{
		if (count( $path )) {
			return [ "result" => false ];
		}
		$post = PostData::CheckAccessKey( $this->args->post );
		if (!$post) {
			return [
				"result" => "<i>Unable to load comments (BadKey)</i>"
			];
		}
		$post = PostData::Load( $post );
		if (!$post) {
			return [
				"result" => "<i>Unable to load comments (BadPost)</i>"
			];
		}
		$cursor = null;
		if ($this->args->start) {
			$s = (int)$this->args->start
			$cursor = "$s:" . self::COMMENTS_PER_PAGE;
		}
		$cl = CommentData::LoadForPost( $post->id, null, $this->args->cursor );
		if (!$cl) {
			return [
				"result" => false
			];
		}

	}


	/*
	=====================
	RequestHandler
	=====================
	*/
	public function RequestHandler()
	{
		$this->user = null;
		if (!empty($_SESSION['userid'])) {
			$this->user = UserData::Load( $_SESSION['userid'] );
		}
		$pp = explode( "/", $this->path );
		$func = count($pp) >= 2 ? $pp[1] : "(empty)";
		if (preg_match( "/^[_a-zA-Z0-9]+$/", $func )) {
			$cmd = "cmd_" . $pp[1];
			if (method_exists( $this, $cmd )) {
				return $this->$cmd( array_slice( $pp, 2 ) );
			}
		}
		return [
			"alert" => "Unknown comment function " . $func;
		];
	}

		$args = $this->args;
		if ($args->logout) {
			session_start();
			session_unset();
			session_destroy();
			return [
				 "run" => 'alert("You have been signed out."); window.location = "/";'
			];
		}
		if (Http::$method !== "POST") {
			Http::MethodNotAllowed();
			return $this->ShowError( "Login form submission error" );
		}
		if ($args->password === null || $args->username === null) {
			return $this->ShowError( "Login form submission error" );
		}
		$user = UserData::LookupUsername( $args->username );
		if (!$user) {
			return $this->ShowError( "Unknown user" );
		}
		if (!$user->CheckPassword( $args->password )) {
			return $this->ShowError( "Incorrect password" );
		}
		session_start();
		$_SESSION['userid'] = $user->id;
		$_SESSION['username'] = $user->GetName();

		$info = [
			 "username" => $user->GetName()
			,"id" => $user->id
		];
		return [
			"run" => 'login_close(' .
					json_encode( $info ) . 
				");"
		];
	}


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
	GetBlog
	=====================
	*/
	function GetBlog()
	{
		if ($this->blog) {
			return $this->blog;
		}
		$u = UserData::Load( $_SESSION['userid'] );
		if (!$u) {
			throw new AppError( "UnknownUser" );
		}
		$this->user = $u;
		// TODO: user allowed to create blog?
		$b = $u->GetBlog();
		if (!$b) {
			$b = BlogData::Create( $u->id );
			$b->Save();
		}
		$this->blog = $b;
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
	NewDraft
	=====================
	*/
	function NewDraft()
	{
		$b = $this->GetBlog();
		$this->html = file_get_contents( "res/blog_draft.html" );
		$this->tokens->screen_title = "New Post";
		$this->tokens->title = "";
		$this->tokens->text = "";
		$this->tokens->dateline = "";
		$this->tokens->postid = "new";
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
		$this->html = file_get_contents( "res/blog_draft.html" );
		$this->tokens->screen_title = "Edit Post";
		$this->tokens->title = $p->GetTitle();
		$this->tokens->text = $p->GetDraft();
		$this->tokens->dateline = $p->GetDateline();
		$this->tokens->postid = $this->args->post;
		$this->tokens->link = "";
		$lid = $p->GetLinkId();
		if ($lid) {
			$l = LinkData::Load( $lid );
			$this->tokens->link = $l->GetLink();
		}
	}

	/*
	=====================
	SavePost
	=====================
	*/
	function SavePost()
	{
		$b = $this->GetBlog();
		$this->replyType = "json";
		$args = $this->args;
		if ($this->method != "POST") {
			throw new AppError( "NotAllowed", "Invalid method" );
		}
		if ($args->post == "new") {
			$args = $this->args;
			$err = null;
			if ($args->title === null || $args->text === null) {
				throw new AppError( "BadParameter" );
			} else {
				$post = $b->CreateDraft( [
					 "title" => $args->title
					,"text" => $args->text
				] );
				$this->SetupLink( $post, $args->link );
				$this->ReplyJson( [
					 "alert" => "Created new draft."
					,"run" => 
						"assign_post_id(\"" . $post->id . "\");" .
						"unmask_post_tools();"
				] );
			}
			return;
		}
		$p = PostData::Load( $args->post );
		if (!$p || $p->GetAuthor() != $b->GetAuthor()) {
			throw new AppError( "NotFound", "Invalid post ID" );
		}

		$err = null;
		$p->SetDraft( $args->text );
		$p->SetTitle( $args->title );
		$p->SetDateline( $args->dateline );
		$this->SetupLink( $p, $args->link );
		$p->Save();

		$this->ReplyJson( [
			 "alert" => "Saved."
			,"run" => "unmask_post_tools();"
		] );
	}


	/*
	=====================
	PublishPost
	=====================
	*/
	function PublishPost()
	{
		$this->replyType = "json";
		$b = $this->GetBlog();
		$p = PostData::Load( $this->args->post );
		if (!$p || $p->GetAuthor() != $b->GetAuthor()) {
			throw new AppError( "NotFound", "Invalid post ID" );
		}
		if ($this->method != "POST") {
			throw new AppError( "NotAllowed", "Invalid method" );
		}
		$ntext = $p->GetDraft();
		$otext = $p->GetText();
		$err = null;
		if ($ntext == "") {
			throw new AppError( "Empty", "Cannot publish an empty draft" );
		}
		if ($otext === $ntext) {
			throw new AppError( "Unchanged", "No changes to publish" );
		}
		$p->SetText( $ntext );
		$p->SetState( "published" );
		$p->Save();
		Cache::remove( "full_post_" . $p->id );
		$l = LinkData::Load( $p->GetLinkId() );
		if ($l) {
			$l->SetType( "blogpost" );
			$l->Save();
		}
		$this->ReplyJson( [
			 "alert" => "Published."
			,"goto" => "-/blog/posts"
		] );
	}

	/*
	=====================
	UnpublishPost
	=====================
	*/
	function UnpublishPost()
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
		Cache::remove( "full_post_" . $p->id );
		$p->SetState( "draft" );
		$p->Save();
		$l = LinkData::Load( $p->GetLinkId() );
		if ($l) {
			$l->SetType( "draft" );
			$l->Save();
		}
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
			throw new AppError( "NotFound", "Invalid post ID" );
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
				,"link" => "-/blog/edit?post=" . $el->id
				,"mtime" => date("Y-m-d H:i:s", $el->GetDraftTimestamp() )
			] );
		}

		$opl = [];
		foreach ($pl as $el) {
			$title = $el->GetTitle();
			if ($title === "") {
				$title = "(untitled)";
			}
			$elink = "-/blog/edit?post=" . $el->id;
			$lid = $el->GetLinkId();
			if ($lid) {
				$link = LinkData::Load( $lid );
				$elink = substr( $link->GetLink(), 1 );
			}
			$opl[] = $this->ReplaceAngTokens( $pt, [
				 "title" => $title
				,"postid" => $el->id
				,"link" => htmlspecialchars( $elink )
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
	ShowViews
	=====================
	*/
	function ShowViews()
	{
		$b = $this->GetBlog();
		$vl = ViewData::Find( [ "*filter" => [ [
			  "type" => "blogmain"
			 ,"subject" => $b->id
		],[ 
			 "type" => "blogpost" 
			,"subject2" => $b->id
		] ] ] );
		//$vml = ViewData::Find( [ 
		//	 "type" => "blogmain" 
		//	,"subject" => $b->id
		//] );
		$rcnl = array_slice( $vl, 0, 50 );
		$recentlist = [];
		foreach ($rcnl as $v) {
			$d = $v->GetInfo();
			$recentlist[] = [
				 "postid" => $d->id
				,"time" => $d->time
				,"referrer" => $v->GetReferrer()
				,"source" => $d->ip
			];
		}
		$this->tokens->recents = $recentlist;
		$this->html = file_get_contents( "res/views_list.html" );
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
			case "unpublish":
				return $this->UnpublishPost();
			case "views":
				return $this->ShowViews();
			case "posts":
				return $this->ListPosts();
			case "drafts":
				return $this->ListDrafts();
			case "gopost":
				return $this->GotoPost();
			case "preview":
				return $this->PreviewPost();
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
