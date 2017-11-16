<?php /* Copyright (C) 2017 David O'Riva. MIT License.
       ********************************************************/
/*
================================================================================

go

Main request distributor. 

================================================================================
*/

namespace Typeractive;

date_default_timezone_set( "UTC" );
spl_autoload_register( function( $name ) {
	//error_log( "autoload $name" );
	if (substr( $name, 0, 12 ) == 'Typeractive\\' ) {
		$name = substr( $name, 12 );
		include( "class/$name.php" );
	} else {
		include( "lib/$name.php" );
	}
} );

require_once( "glib.php" );

register_shutdown_function( function() {
	// TODO: finish logs, handle fatal, etc...
} );

// TODO: Reimplement as "default" of SecretStore?
$gSecrets = null;

/*
=====================
EmitSitePage

Renders a "standard" site page with the given HTML.
The page includes basic support script and css, a loader mask, and a site
header with menu/identity block.
=====================
*/
function EmitSitePage( $parts )
{
	$frm = file_get_contents( "res/site_frame.html" );
	$css = file_get_contents( "res/site_frame.css" );
	$js = file_get_contents( "res/main.js" );
	$hdr = file_get_contents( "res/site_header.html" );
	$ftr = file_get_contents( "res/site_footer.html" );
	$ext = file_get_contents( "res/ext_dialog.html" );

	/* Note: At some point it would be possible to parse out the various 
	   embedded <style> tags and move them to the head, but meh. */

	$parts = (object)$parts;
	$body = empty( $parts->html ) ? "" : $parts->html;
	if (!empty( $parts->js )) {
		$js .= $parts->js;
	}
	if (!empty( $parts->css )) {
		$css .= $parts->css;
	}
	$title = empty( $parts->title ) ? "Untitled" : $parts->title;

	$hdr = str_replace( "{{approot}}", Http::$appRootUrl, $hdr );
	$ftr = str_replace( "{{approot}}", Http::$appRootUrl, $ftr );

	$out = str_replace( [
			"{{header}}",
			"{{css}}",
			"{{script}}",
			"{{footer}}",
			"{{title}}",
			"{{body}}",
			"{{html-extensions}}"
		], [
			$hdr,
			$css,
			$js,
			$ftr,
			$title,
			$body,
			$ext
		], 
		$frm
	 );
	echo $out;
}

/*
=====================
Launch

Outer distributor for requests.
=====================
*/
function Launch()
{
	global $gSecrets;

	Http::SetupRequest();
	if (Http::$path == "" && Http::Folderize()) {
		exit();
	}

	$gSecrets = new SecretStore( "res/secrets" );
	if (!$gSecrets->Exists()) {
		$gSecrets->Reset( gethostname() );
	}
	if (!$gSecrets->Open( gethostname() )) {
		echo "Site misconfigured";
		die;
	}

	// Global database config
	$dbconfig = $gSecrets->Get( "database" );
	if (!$dbconfig) {
		$dbconfig = [
			 "username" => "root"
			,"password" => ""
			,"host" => "127.0.0.1"
			,"database" => "typeractive"
		];
	}
	Sql::AutoConfig( $dbconfig );

	SqlShadow::DefineTable( "auth", ["autoindex" => "authid"] );
	SqlShadow::DefineTable( "blogs", ["autoindex" => "blogid"] );
	SqlShadow::DefineTable( "users", ["autoindex" => "userid"] );
	SqlShadow::DefineTable( "links", ["autoindex" => "linkid"] );
	SqlShadow::DefineTable( "text", ["autoindex" => "textid"] );
	SqlShadow::DefineTable( "posts", ["autoindex" => "postid"] );

	$pp = explode( '/', Http::$path, 3 );
	$p1 = (count( $pp ) > 1) ? $pp[1] : "";
	$p2 = (count( $pp ) > 2) ? $pp[2] : "";
	$req = [
		 "args" => $_REQUEST
		,"path" => $p2
		,"headers" => Http::$headers
	];
	switch ($p1) {
	case "":
		MainPageServer::Handle( $req );
		return;
	case "-":
		$pp = explode( '/', $p2, 2 );
		$p2 = $pp[0];
		$pp[0] = "";
		$req['path'] = implode( "", $pp );
		switch ($p2) {
		case "login":
			return LoginServer::Handle( $req );
		case "dashboard":
			return DashboardServer::Handle( $req );
		case "blog":
			return BlogEditor::Handle( $req );
		}
		break;
	case "user":
		break;
	case "--bootstrap--":
		return Bootstrap::Serve( implode( '/', array_slice( $pl, 2 ) ) );
	}
	$l = LinkData::Lookup( Http::$path );
	if (!$l) {
		Http::NotFound();
		Http::ContentType( "text/plain" );
		echo "404 Not Found";
		return;
	}
	$b = Blog::Open( $l->GetReference() );
	$b->RenderPage( implode( '/', $rest ) );
}

Launch();
