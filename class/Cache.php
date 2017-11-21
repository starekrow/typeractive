<?php /* Copyright (C) 2017 David O'Riva. MIT License.
       ********************************************************/
namespace Typeractive;

/*
================================================================================

Cache - data caching subsystem

This class provides an interface for a "default" cache instance and methods to
create such an instance.

* connect - establish access to a cache
* setDefault - set the default instance

There is also a static interface that uses the current default cache instance
for common operations: get, set, setx, remove, wait, lock and unlock.

================================================================================
*/
class Cache
{
	protected static $cache;

	/*
	=====================
	connect

	Connects to a cache. If no parameters are supplied, returns the current
	default cache instance (see `setDefault`).

	If successful, returns an object implementing CacheInterface.
	Otherwise returns `false`
	=====================
	*/
	public static function connect( $type = null, $options = null )
	{
		if ($type === null) {
			return self::$cache ? self::$cache : false;
		}
		$cn = "Typeractive\\Cache\\d_$type";
		$c = new $cn();
		return $c->connect( $options ) ? $c : false;
	}

	/*
	=====================
	setDefault

	Sets the default cache instance, possibly creating it first.
	=====================
	*/
	public static function setDefault( $cache = null, $options = null )
	{
		// TODO: delay instantiation until first use
		if ($cache === null) {
			self::$cache = null;
		} else if ($cache instanceof Cache\CacheDriver) {
			self::$cache = $cache;
		} else {
			self::$cache = self::connect( $cache, $options );
		}
		return self::$cache;
	}

	/*
	=====================
	set
	=====================
	*/
	public static function set( $key, $value, $ttl = null )
	{
		if (self::$cache) {
			self::$cache->set( $key, $value, $ttl );
		}
	}

	/*
	=====================
	setx
	=====================
	*/
	public static function setx( $key, $value, $ttl = null )
	{
		if (self::$cache) {
			self::$cache->setx( $key, $value, $ttl );
		}
	}

	/*
	=====================
	get
	=====================
	*/
	public static function get( $key )
	{
		if (self::$cache) {
			return self::$cache->get( $key );
		}
		return false;
	}

	/*
	=====================
	lock
	=====================
	*/
	public static function lock( $key, $wait = null, 
		&$val = null, $threshold = null, $limit = null )
	{
		if (self::$cache) {
			return self::$cache->lock( $key, $wait, $val, $threshold, $limit );
		}
		return false;
	}
	/*
	=====================
	wait
	=====================
	*/
	public static function wait( $key, $limit = null )
	{
		if (self::$cache) {
			return self::$cache->wait( $key, $limit );
		}
		return false;
	}

	/*
	=====================
	remove
	=====================
	*/
	public static function remove( $key )
	{
		if (self::$cache) {
			return self::$cache->remove( $key );
		}
		return false;
	}

	/*
	=====================
	unlock
	=====================
	*/
	public static function unlock( $key )
	{
		if (self::$cache) {
			return self::$cache->unlock( $key );
		}
		return false;
	}

}

