<?php /* Copyright (C) 2017 David O'Riva. All rights reserved.
       ********************************************************/
namespace Typeractive;

/*
================================================================================

Cache - data caching subsystem

This class provides an interface for a "default" cache instance and methods to
create such an instance.

* connect - establish access to a cache
* use - set the default instance

================================================================================
*/
class Cache
{
	protected static $cache;

	/*
	=====================
	connect

	Connects to a cache. If no parameters are supplied, returns the current
	"default" cache instance as set by use().

	If successful, returns an object implementing CacheInterface.
	Otherwise returns `false`
	=====================
	*/
	public static function connect( $type = null, $options = null )
	{
		if ($type === null) {
			return self::$cache ? self::$cache : false;
		}
		// TODO: invoke static factory on driver
		$cn = "Typeractive\\Cache\\$type";
		return new $cn( $options );
		// TODO: return "null" cache driver if creation fails?
	}

	/*
	=====================
	prep

	Sets the "default" cache instance, possibly creating it first.
	=====================
	*/
	public static function prep( $cache = null, $options = null )
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

}

