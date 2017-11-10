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
	error_log( "autoload $name" );
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

function Launch()
{
	global $gSecrets;

	Http::SetupRequest();
	if (Http::$path == "" && Http::Folderize()) {
		exit();
	}

	$gSecrets = new SecretStore( "res/secrets" );
		$gSecrets->Reset( gethostname() );
	if (!$gSecrets->Exists()) {
		$gSecrets->Reset( gethostname() );
	} else {
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
	SqlShadow::DefineTable( "blogs", Blog::$tableDef );
	SqlShadow::DefineTable( "permalinks", Permalink::$tableDef );

	$pl = explode( '/', Http::$path, 4 );
	$rest = [];
	$l = Permalink::Lookup( Http::$path );
	if (!$l) {
		$l = Permalink::Lookup( implode( '/', array_slice( $pl, 0, 2 ) ) );
		$rest = array_slice( $pl, 2 );
	}
	if (!$l) {
		if ($pl[1] == '--bootstrap--') {
			return Bootstrap::Serve( implode( '/', array_slice( $pl, 2 ) ) );
		}
		Http::NotFound();
		Http::ContentType( "text/plain" );
		echo "404 Not Found";
		return;
	}
	$b = Blog::Open( $l->GetReference() );

	$b->RenderPage( implode( '/', $rest ) );
}

Launch();
