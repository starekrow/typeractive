<?php /* Copyright (C) 2017 David O'Riva. MIT License.
       ********************************************************/
namespace Typeractive;

/*
================================================================================

Http

Helper functions for web sites.

================================================================================
*/

class Http
{
	public static $url;
	public static $appRootUrl;
	public static $fullPath;
	public static $path;
	public static $host;
	public static $query;
	public static $queryString;
	public static $secure;
	public static $referrer;
	public static $headers;
	public static $timestamp;
	public static $source;
	public static $method;

	/*
	=====================
	Redirect

	Sets the `Location` header and response code. Obviously only works if 
	invoked before any output.
	=====================
	*/
	public static function Redirect( $url, $temporary = false, $code = null )
	{
		if (!$code) {
			$code = $temporary ? 302 : 301;
		}
		header( 'Location: ' . $url, true, $code );
	}

	/*
	=====================
	Folderize

	If the path part of the current URL does not end with "/", redirect with
	a "/" appended. This sets all relative URLs accessed from the page *below*
	the current path instead of alongside the last part.

	This is done with a 301 permanent redirect.

	Returns true if the redirect was done.
	=====================
	*/
	public static function Folderize()
	{
		$parts = explode( "?", self::$url );
		if ($parts[0][ strlen($parts[0]) - 1 ] != '/') {
			$parts[0] .= '/';
			$url = implode( '?', $parts );
			self::Redirect( $url );
			return true;
		}
		return false;
	}

	/*
	=====================
	StopClientCache

	Sets headers to instruct the client not to cache this response.
	=====================
	*/
	public static function StopClientCache()
	{
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Expires: Sat, 08 Oct 1995 19:00:00 GMT' );
		header( 'Pragma: no-cache' );
	}

	/*
	=====================
	SetClientCache

	Allow client to cache response for given number of seconds.
	=====================
	*/
	public static function SetClientCache( $seconds )
	{
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Expires: Sat, 08 Oct 1995 19:00:00 GMT' );
	}

	/*
	=====================
	Some response code functions to help with readability.
	=====================
	*/
	public static function rcContinue() 		{ http_response_code( 100 ); }

	public static function Created() 			{ http_response_code( 201 ); }
	public static function Accepted() 			{ http_response_code( 202 ); }
	public static function NoContent() 			{ http_response_code( 204 ); }
	public static function ResetContent() 		{ http_response_code( 205 ); }
	public static function PartialContent() 	{ http_response_code( 206 ); }

	public static function MovedPermanently() 	{ http_response_code( 301 ); }
	public static function MovedTemporarily() 	{ http_response_code( 302 ); }
	public static function SeeOther() 			{ http_response_code( 303 ); }
	public static function NotModified() 		{ http_response_code( 304 ); }

	public static function BadRequest() 		{ http_response_code( 400 ); }
	public static function Unauthorized() 		{ http_response_code( 401 ); }
	public static function Forbidden()	 		{ http_response_code( 403 ); }
	public static function NotFound()	 		{ http_response_code( 404 ); }
	public static function MethodNotAllowed()	{ http_response_code( 405 ); }
	public static function NotAcceptable()		{ http_response_code( 406 ); }
	public static function Conflict()			{ http_response_code( 409 ); }
	public static function Gone()				{ http_response_code( 410 ); }

	public static function InternalError()		{ http_response_code( 500 ); }
	public static function InternalServerError(){ http_response_code( 500 ); }
	public static function NotImplemented()		{ http_response_code( 501 ); }
	public static function BadGateway()			{ http_response_code( 502 ); }
	public static function ServiceUnavailable()	{ http_response_code( 503 ); }
	public static function GatewayTimeout()		{ http_response_code( 504 ); }

	/*
	=====================
	ContentType

	Sets (or overrides) the content type header.
	=====================
	*/
	public static function ContentType( $type )
	{
		header( "Content-Type: $type", true );
	}

	/*
	=====================
	SetupRequest

	Calculates some stuff from the request for general use.
	=====================
	*/
	public static function SetupRequest()
	{
		self::$path = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : "";
		self::$query = new Dict( $_REQUEST );
		$url = $_SERVER['REQUEST_URI'];
		$uparts = explode( "?", $url );
		self::$queryString = count($uparts) > 1 ? $uparts[1] : null;
		self::$secure = !empty( $_SERVER['HTTPS'] );
		$scheme = self::$secure ? 'https' : 'http';
		$host = self::$host = $_SERVER['HTTP_HOST'];
		self::$url = "$scheme://$host$url";
		self::$fullPath = $uparts[0];
		$approot = substr($uparts[0], 0, strlen( $uparts[0] ) - 
			strlen( self::$path ));
		self::$appRootUrl = "$scheme://$host$approot";
		self::$referrer = 
			isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null;
		if (!empty( $_SERVER['REQUEST_TIME_FLOAT'] )) {
			self::$timestamp = $_SERVER['REQUEST_TIME_FLOAT'];
		} else {
			self::$timestamp = $_SERVER['REQUEST_TIME'];
		}
		self::$source = $_SERVER['REMOTE_ADDR'];

		$hdrs = new Dict();
		foreach ($_SERVER as $k=>$v) {
			if (substr( $k, 0, 5 ) == "HTTP_") {
				$hdrs[ strtolower(str_replace("_", "-", substr($k, 5))) ] = $v;
			}
		}
		self::$headers = $hdrs;
		self::$method = strtoupper( $_SERVER['REQUEST_METHOD'] );
	}

	/*
	=====================
	Emit

	Fancy wrapper for echo.
	=====================
	*/
	public static function Emit()
	{
		$l = func_get_args();
		foreach ($l as $el) {
			echo $l;
		}
	}

}
