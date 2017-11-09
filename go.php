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
	echo "ack failed";
	// ... finish logs ...
} );

$gSecrets = null;

function Launch()
{
	global $gSecrets;

	Http::SetupRequest();
	if (Http::$path === "") {
		// Needed to make sure that relative URLs work right if we're not
		// at the domain root.
		$to = Http::$appRootUrl . '/';
		if (Http::$queryString !== "") {
			$to .= "?" . Http::$queryString;
		}
		Http::Redirect( $to );
		exit();
	}

	$gSecrets = new SecretStore( "res/secrets" );
	if (!$gSecrets->Exists()) {
		$gSecrets->Reset();
	}

	// Global database config
	$dbconfig = $gSecrets->Get( "database" );
	if (!$dbconfig) {
		$dbconfig = [
			 "username" => "root"
			,"password" => ""
		];
	}
	Sql::AutoConfig( $dbconfig );

	$b = Blog::Open( "starekrow" );
	$b->RenderPage( 4 );
}

Launch();
