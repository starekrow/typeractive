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

class ClassLoader
{
	static $map = [
		 "Typeractive\\BlogEditor" => "class/Servers/BlogEditor.php"
		,"Typeractive\\PageEditor" => "class/Servers/PageEditor.php"
	];
	static function Load( $name ) {
		//error_log( "autoload $name" );
		if (!empty( self::$map[ $name ] )) {
			include( self::$map[ $name ] );
			return;
		}
		if (substr( $name, 0, 12 ) == 'Typeractive\\' ) {
			$name = str_replace( "\\", "/", substr( $name, 12 ) );
			if (!@include( "class/$name.php" )) {
				//error_log( "autoload - not found 1" );
				if (substr( $name, -4 ) == "Data") {
					include( "class/Data/$name.php" );
				} else if (substr( $name, -6 ) == "Server") {
					include( "class/Servers/$name.php" );
				}
			}
		} else {
			include( "lib/$name.php" );
		}
	}
}
spl_autoload_register( "Typeractive\\ClassLoader::Load" );

require_once( "glib.php" );

register_shutdown_function( function() {
	// TODO: finish logs, handle fatal, etc...
} );

// TODO: Reimplement as "default" of SecretStore?
$gSecrets = null;

/*
=====================
NormalizePath

Normalizes a path-like string, processing ".." and ".", removing multiple
"/" and converting all "\" to "/". Leading and trailing "/", if any, are 
retained.
=====================
*/
function NormalizePath( $path )
{
	$path = str_replace( "\\", "/", $path );
	$path = preg_replace( "|/+|", "/", $path );
	if ($path === "") {
		return $path;
	}
	$start = 0;
	$end = 0;
	if ($path[0] != "/") {
		$path = "/$path";
		$start = 1;
	}
	if ($path[ strlen($path) - 1 ] != "/") {
		$path = "$path/";
		$end = 1;
	}
	for (;;) {
		$res = preg_replace( "|/\./|", "/", $path );
		if ($res === $path) {
			break;
		}
		$path = $res;
	}
	for (;;) {
		$res = preg_replace( "|[^/]+/\.\./|", "", $path );
		if ($res === $path) {
			break;
		}
		$path = $res;
	}
	return substr( $path, $start, strlen( $path ) - $start - $end );
}


/*
=====================
ResolvePath

Resolves a path to a link type, an ID and a remaining fragment.
The path must start with "/". If only part of the path is used during 
resolution, the remainder will be returned in a "path" property. This 
remainder will *always* have a "/" at the beginning unless it is empty.
Links must be stored without a trailing "/".
=====================
*/
function ResolvePath( $path )
{
	$path = str_replace( "\\", "/", $path );
	if ($path[0] != "/") {
		$path = "/$path";
	}
	// Don't mess around with bad paths
	if (   ($path !== "" && $path[0] != "/") 
		|| strlen( $path ) > 200 
		|| strpos( $path, "/." ) !== false
	   ) {
		return (object) [
			 "type" => "illegal"
			,"link" => ""
			,"path" => ""
			,"id" => null
		];
	}
	// peel off bootstrap to prevent DB access
	if (substr( $path, 0, 14 ) == "/--bootstrap--") {
		return (object)[
			 "type" => "bootstrap"
			,"link" => "/--bootstrap--"
			,"path" => substr( $path, 14 )
			,"id" => null
		];
	}
	// peel off existing content to prevent unnecessary DB access
	if (substr( $path, 0, 9 ) == "/content/") {
		// quick check for actual file
		$path = substr( $path, 8 );
		$path = str_replace( "\\", "/", $path );
		// no trickery allowed
		if (strpos( $path, "/." ) !== false) {
			$path = "/--error--";
		}
		if (file_exists( "content$path" )) {
			return (object)[
				 "type" => "content"
				,"link" => $path
				,"path" => ""
				,"id" => null
			];
		}
	}
	$chk = Cache::get( "link:$path" );
	if ($chk) {
		return $chk;
	}
	$link = LinkData::Lookup( $path );
	if ($link) {
		$res = (object)[
			 "type" => $link->GetType()
			,"id" => $link->GetReference()
			,"link" => $link->GetLink()
			,"path" => ""
		];
		Cache::set( "link:$path", $res );
		return $res;
	}
	$path = NormalizePath( $path );

	$pp = explode( "/", $path );
	/* TODO: finish this
	for ($i = count( $pp ); $i > 0; ++$i) {
		$psub = implode( "/", array_slice( $path, 0, $i ) );
		$link = LinkData::Lookup( $psub );
		if ($link) {
			$rem = array_slice( $path, $i );
			$pre = count( $rem ) ? "/" : "";
			return (object)[
				 "type" => $link->GetType()
				,"id" => $link->GetReference()
				,"link" => $link->GetLink()
				,"path" => $pre . implode( "/", $rem );
			];
		}

	}
	*/
	// No luck in link table, check built-in rules
	if ($path === "" || $path === "/") {
		return (object)[
			 "type" => "root"
			,"id" => null
			,"link" => $path
			,"path" => ""
		];
	}
	// private management 
	if ($pp[1] == "-" && count($pp) >= 3) {
		session_start();
		$tp = "-" . $pp[2];
		return (object) [
			 "type" => "-" . $pp[2]
			,"link" => implode( "/", array_slice( $pp, 0, 3 ) )
			,"path" => "/" . implode( "/", array_slice( $pp, 3 ) )
			,"id" => empty( $_SESSION['userid'] ) ? null : $_SESSION['userid']
		];
	}
	return (object)[
		 "type" => "unknown"
		,"id" => null
		,"link" => null
		,"path" => $path
	];
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
	SqlShadow::DefineTable( "pages", ["autoindex" => "pageid"] );
	SqlShadow::DefineTable( "posts", ["autoindex" => "postid"] );

	Cache::setDefault( "file" );
	
	$r = ResolvePath( Http::$path );

	$req = [
		 "args" => $_REQUEST
		,"path" => $r->path
		,"link" => $r->link
		,"type" => $r->type
		,"id" => $r->id
		,"headers" => Http::$headers
		,"method" => Http::$method
		,"referrer" => Http::$referrer
		,"source" => Http::$source
	];


	switch ($r->type) {
	case "bootstrap":
		return Bootstrap::Serve( $req );
	case "content":
		return ContentServer::Handle( $req );
	case "root":
		return MainPageServer::Handle( $req );
	case "-login":
		return LoginServer::Handle( $req );
	case "-dashboard":
		return DashboardServer::Handle( $req );
	case "-blog":
		return BlogEditor::Handle( $req );
	case "-pages":
		return PageEditor::Handle( $req );
	case "-job":
		return JobServer::Handle( $req );
	case "blogpost":
		return BlogServer::Handle( $req );
	case "blogmain":
		return BlogServer::Handle( $req );
	case "page":
		return PageServer::Handle( $req );
	case "user":
	default:
		Http::NotFound();
		Http::ContentType( "text/plain" );
		echo "404 Not Found";
		return;	
	}
}

Launch();
