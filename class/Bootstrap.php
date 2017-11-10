<?php /* Copyright (C) 2017 David O'Riva. MIT License.
       ********************************************************/
namespace Typeractive;

/*
================================================================================

Bootstrap

Low-level/first-time administration functions

================================================================================
*/

class Bootstrap
{
	/* no instances allowed */
	protected function __construct() {}

	/*
	=====================
	CheckHttpAuth
	=====================
	*/
	public function CheckHttpAuth( $path )
	{
	}

	/*
	=====================
	Signout
	=====================
	*/
	public function Signout()
	{
		session_unset();
		session_destroy();
	}

	/*
	=====================
	RequestAuth
	=====================
	*/
	public function RequestAuth( $failed = false )
	{
		header('WWW-Authenticate: Basic realm="Typeractive Bootstrap Admin"');
		Http::Unauthorized();
		readfile( "res/bootstrap_declined_auth.html" );
		return false;
	}

	/*
	=====================
	CheckAuth
	=====================
	*/
	public static function CheckAuth()
	{
		global $gSecrets;

		if (!empty( $_SESSION['bootstrap_auth'] )) {
			return true;
		}
		$adpass = null;
		$auth = $gSecrets->Get( "bootstrap" );
		if (!$auth) {
			$adpass = trim( file_get_contents( "res/bootstrap_password.txt" ) );
			if (!$adpass) {
				include "res/bootstrap_noauth.html";
				return false;
			}
		}
		if (!isset($_SERVER['PHP_AUTH_USER'])) {
			return self::RequestAuth();
		}
    	$username = $_SERVER['PHP_AUTH_USER'];
    	$password = $_SERVER['PHP_AUTH_PW'];
		if (!$auth) {
			if ($password != $adpass) {
				password_hash( $password, PASSWORD_BCRYPT, [ "cost" => 12 ] );
				return self::RequestAuth();
			}
    	} else {
	    	if (empty( $auth[ $username ] )) {
				password_hash( $password, PASSWORD_BCRYPT, [ "cost" => 12 ] );
	    		return self::RequestAuth();
	    	}
	    	if (!password_verify( $password, $auth[ $username ] )) {
	    		return self::RequestAuth();	    		
	    	}
	    }
	    $_SESSION['bootstrap_auth'] = true;
	    return true;
	}

	/*
	=====================
	Page_test
	=====================
	*/
	public static function Page_test( $path )
	{	
		Http::ContentType( "text/plain" );
		echo "OK\r\n";
		echo $path;	
	}

	/*
	=====================
	Page_dbsetup
	=====================
	*/
	public static function Page_dbsetup( $path )
	{	
		if ($path == "") {
			self::RenderPage( file_get_contents( "res/bootstrap_dbsetup.html" ) );
			return;
		}
		echo "do setup $path\r\n";
		echo $path;	
	}

	/*
	=====================
	RenderPage
	=====================
	*/
	public static function RenderPage( $content )
	{
		if (is_array( $content )) {
			$content = implode( "", $content );
		}
		$html = file_get_contents( "res/bootstrap_frame.html" );
		$html = str_replace( "{{content}}", $content, $html );
		echo $html;
	}

	/*
	=====================
	Serve
	=====================
	*/
	public static function Serve( $path )
	{		
		if ($path == "" && Http::Folderize()) {
			exit();
		}

		session_start();

		if (!self::CheckAuth()) {
			return false;
		}
		if ($path == "") {
			self::RenderPage( file_get_contents( "res/bootstrap_main.html" ) );
			return;
		}
		$pp = explode( '/', $path );
		if (preg_match( '/^[-_a-zA-Z0-9]+$/', $pp[0] )) {
			$mth = 'Page_' . str_replace( '-', '_', $pp[0] );
			$cls = get_called_class();
			if (method_exists( $cls, $mth )) {
				$cls::{$mth}( implode( '/', array_slice( $pp, 1 ) ) );
				return;
			}
		}
		Http::NotFound();
		echo "404 Not Found";
	}
}
