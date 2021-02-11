<?php /* Copyright (C) 2017 David O'Riva. MIT License
       ********************************************************/
namespace Typeractive\Cache;

/*
================================================================================

null - Cache driver that doesn't cache anything

================================================================================
*/
class d_null extends CacheDriver
{
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
	}

	/*
	=====================
	getRaw
	Looks up a key in the cache.  Return the key or `false` if not found.
	=====================
	*/
	protected function getRaw( $key )
	{
		return false;
	}

	/*
	=====================
	setRaw
	Sets a key in the cache.
	=====================
	*/
	protected function setRaw( $key, $value, $ttl )
	{
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
	}

	/*
	=====================
	removeAll
	Remove all keys from the cache
	=====================
	*/
	protected function removeAll()
	{
	}
}


