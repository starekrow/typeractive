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
	    	if (empty( $auth->$username )) {
				password_hash( $password, PASSWORD_BCRYPT, [ "cost" => 12 ] );
	    		return self::RequestAuth();
	    	}
	    	if (!password_verify( $password, $auth->$username )) {
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
		global $gSecrets;
		if ($path == "") {
			$html = file_get_contents( "res/bootstrap_dbsetup.html" );
			$dc = $gSecrets->Get( "database" );
			if (!$dc) {
				$dc = [
					 "username" => "root"
					,"password" => ""
					,"host" => "127.0.0.1"
					,"database" => "typeractive"
				];
			}
			$dc = new Dict( $dc );
			$html = str_replace( [
					 "{{host}}"
					,"{{port}}"
					,"{{database}}"
					,"{{username}}"
					,"{{password}}"
				], [
					 $dc->host
					,$dc->port
					,$dc->database
					,$dc->username
					,$dc->password
				], $html
			);
			self::RenderPage( $html, "DB Setup", true );
			return;
		} else if ($path == "post") {
			$pl = ["username", "database", "password", "host", "port"];
			$vals = array_intersect_key( $_REQUEST, array_fill_keys( $pl, 1 ) );
			$gSecrets->Put( "database", $vals );
			$res = [
				"run" => "alert( 'Settings saved.' );"
			];
			//var_dump( $pl );
			echo json_encode( $res );
			return;
		}
		echo "do setup '$path'\r\n";
	}

	/*
	=====================
	Page_adminuser
	=====================
	*/
	public static function Page_adminuser( $path )
	{
		global $gSecrets;
		if ($path == "") {
			$html = file_get_contents( "res/bootstrap_adminuser.html" );
			self::RenderPage( $html, "Admin User", true );
			return;
		} else if ($path == "post") {
			$un = $_REQUEST['username'];
			$pw = $_REQUEST['password'];

			$un = preg_replace( "/ +/", " ", trim( $un ) );
			if (!User::ValidateUsername( $un )) {
				echo json_encode( [ "run" => "alert( 'Invalid username' );" ] );
				return;
			}
			$pw = trim( $pw );
			if (!User::ValidatePassword( $pw )) {
				echo json_encode( [ "run" => "alert( 'Invalid password' );" ] );
				return;
			}
			$pw = password_hash( $pw, PASSWORD_BCRYPT, [ "cost" => 12 ] );

			if (!User::Create( $un, $pw )) {
				echo json_encode( [ "run" => "alert( 'Unable to create user' );" ] );
				return;
			}

			$ul = $gSecrets->Get( "bootstrap" );
			if (!$ul) {
				$ul = (object)[];
			}
			$ul->$un = $pw;
			$gSecrets->Put( "bootstrap", $ul );
			echo json_encode( [ "run" => "alert( 'User $un created' );" ] );
			return;
		}		
		Http::NotFound();
		echo "404 Not Found";
	}

	/*
	=====================
	RenderPage
	=====================
	*/
	public static function RenderPage( $content, $title, $back = false )
	{
		if (is_array( $content )) {
			$content = implode( "", $content );
		}
		$html = file_get_contents( "res/bootstrap_frame.html" );
		$html = str_replace( "{{content}}", $content, $html );
		$html = str_replace( "{{title}}", $title, $html );
		if (!$back) {
			$html = str_replace( "{{backtype}}", "hidden", $html );
		} else {
			$html = str_replace( "{{backtype}}", "back", $html );
			$backstr = ($back === true) ? "Back" : $back;
			$html = str_replace( "{{back}}", $backstr, $html );
			$html = str_replace( "{{backto}}", "", $html );
		}
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
			$main = file_get_contents( "res/bootstrap_main.html" );
			self::RenderPage( $main, "Typeractive Bootstrap" );
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
