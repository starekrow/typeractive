<?php /* Copyright (C) 2017 David O'Riva. MIT License.
       ********************************************************/
/*
================================================================================

go

Main request distributor. 

================================================================================
*/

namespace Typeractive;

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

// Sort out how to handle the request
$dests = [
	 [ "path1" => "|blogs|",
	   "!run" => "blog/draw",
	   "!strip_path" => 1 ]
	,[ "path1" => "|wptest|",
	   "!run" => "webpanel/test",
	   "!strip_path" => 1 ]
	,[ "path1" => "|krod|",
	   "!run" => "krod/main",
	   "!strip_path" => 1 ]
	//,[ ]
];

register_shutdown_function( function() {
	//echo "ack failed";
	// ... finish logs ...
} );

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

	$parts = (object)$parts;
	$body = empty( $parts->html ) ? "" : $parts->html;
	if (!empty( $parts->js )) {
		$js .= $parts->js;
	}
	if (!empty( $parts->css )) {
		$css .= $parts->css;
	}
	$title = empty( $parts->title ) ? "Untitled" : $parts->title;

	$out = str_replace( [
			"{{header}}",
			"{{css}}",
			"{{script}}",
			"{{footer}}",
			"{{title}}",
			"{{body}}"
		], [
			$hdr,
			$css,
			$js,
			$ftr,
			$title,
			$body
		], 
		$frm
	 );
	echo $out;
}



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
	SqlShadow::DefineTable( "blogs", Blog::$tableDef );
	SqlShadow::DefineTable( "users", ["autoindex" => "userid"] );
	SqlShadow::DefineTable( "permalinks", Permalink::$tableDef );
	SqlShadow::DefineTable( "text", ["autoindex" => "textid"] );
	SqlShadow::DefineTable( "posts", ["autoindex" => "postid"] );

	$pp = explode( '/', Http::$path, 3 );
	$p1 = (count( $pp ) > 1) ? $pp[1] : "";
	$p2 = (count( $pp ) > 2) ? $pp[2] : "";
	switch ($p1) {
	case "":
		EmitSitePage( [ "html" => "hi" ] );
		return;
	case "user":
		break;
	case "--bootstrap--":
		return Bootstrap::Serve( implode( '/', array_slice( $pl, 2 ) ) );
	}
	$l = Permalink::Lookup( Http::$path );
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
