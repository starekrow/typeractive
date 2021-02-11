<?php /* Copyright (C) 2017 David O'Riva.  MIT License.
       ********************************************************/
namespace Typeractive\Cache;

/*
================================================================================

file - Cache driver for local file system

================================================================================
*/
class d_file extends CacheDriver
{
	const ROOTDIR = "/tmp/wwwcache";
	protected $mc;

	/*
	=====================
	connect

	Connect to a cache instance.
	Returns true if the connection succeeds (or may succeed in the future).
	=====================
	*/
	public function connect( $options = null )
	{
		if (!is_dir( self::ROOTDIR )) {
			if (!mkdir( self::ROOTDIR, 0700 )) {
				return false;
			}
		}
		return true;
	}

	/*
	=====================
	keyToPath
	Converts a key name to a path name. Some attempt is made to keep the 
	names similar to aid in debugging.
	=====================
	*/
	protected function keyToPath( $key )
	{
		$v = substr( (string) $key, 0, 20 );
		if ($v === "")  $v = "_";
		$v = preg_replace( "/[^-_.a-zA-Z0-9]+/", "_", $v );
		if ($v[0] == ".") $v = "_$v";
		if ($v === $key) {
			return self::ROOTDIR . "/" . $v;
		}
		return self::ROOTDIR . "/" . $v . "@/" . 
			str_replace("/", "_", base64_encode( sha1($key) ));
	}


	/*
	=====================
	getRaw
	Looks up a key in the cache.  Return the key or `false` if not found.
	=====================
	*/
	protected function getRaw( $key )
	{
		$fn = $this->keyToPath( $key );
		if (@filemtime( $fn ) < time()) {
			// Race here
			@unlink( $fn );
			return false;
		}
		$got = @file_get_contents( $fn );
		return $got ? unserialize( $got ) : false;
	}

	/*
	=====================
	setRaw
	Sets a key in the cache.
	=====================
	*/
	protected function setRaw( $key, $value, $ttl )
	{
		$exp = microtime(true) + $ttl;
		$tf = tempnam( self::ROOTDIR, "nw@" );
		if (!@file_put_contents( $tf, serialize( $value ) )) {
			@unlink( $tf );
			return false;
		}
		touch( $tf, (int)$exp + 1 );
		$fn = $this->keyToPath( $key );
		error_log( $fn );
		if (@rename( $tf, $fn )) {
			return true;
		}
		if (!is_dir( dirname($fn) )) {
			@mkdir( dirname( $fn ), 0700, true );
			if (@rename( $tf, $fn )) {
				return true;
			}
		}
		@unlink( $tf );
		return false;
	}

	/*
	=====================
	setRawExclusive
	Sets a key in the cache if it does not already exist. Returns `true` if the 
	key was created, otherwise `false`.
	=====================
	*/
	protected function setRawExclusive( $key, $value, $ttl )
	{
		$fn = $this->keyToPath( $key );
		$now = time();
		if (@filemtime( $fn ) >= $now) {
			return false;
		}
		$exp = $now + $ttl;
		$tf = tempnam( self::ROOTDIR, "nw@" );
		if (!@file_put_contents( $tf, serialize( $value ) )) {
			@unlink( $tf );
			return false;
		}
		touch( $tf, (int)$exp + 1 );
		if (@filemtime( $fn ) >= time()) {
			@unlink( $tf );
			return false;
		}
		// race here
		if (@rename( $tf, $fn )) {
			return true;
		}
		if (!is_dir( dirname($fn) )) {
			@mkdir( dirname( $fn ), 0700, true );
			if (@rename( $tf, $fn )) {
				return true;
			}
		}
		@unlink( $tf );
		return false;
	}

	/*
	=====================
	removeRaw
	Remove or invalidate a key in the cache.
	=====================
	*/
	protected function removeRaw( $key )
	{
		@unlink( $this->keyToPath( $key ) );
	}

	/*
	=====================
	removeAll
	Remove all keys from the cache
	=====================
	*/
	protected function removeAll()
	{
		system( "rm -rf " . self::ROOTDIR . "/*" );
	}
}


