<?php 

namespace mod_core;

include "CryptoKey.php";
include "Secret.php";
include "SecretStore.php";

//$ss = new SecretStore( "/var/www/secrets" );
$ss = new SecretStore( "." );
$ss->Reset( "foobar" );
$ss->Put( "thing", "seekrit" );
//var_dump( $ss );
//die();

//$ss = new SecretStore( "/var/www/secrets" );
$ss = new SecretStore( "." );
if (!$ss->Open( "foobar" )) {
	die( "open store failed\n" );

}

$got = $ss->Get( "thing" );
echo "answer:\n";
var_dump( $got );