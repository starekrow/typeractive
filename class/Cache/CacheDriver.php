<?php /* Copyright (C) 2017 David O'Riva. All rights reserved.
       ********************************************************/
namespace Typeractive\Cache;

/*
================================================================================

CacheDriver - caching subsystem abstraction

Provides high- and mid-level cache management, and defines an abstract low-level
driver interface.

================================================================================
*/
abstract class CacheDriver
{
	const DEFAULT_TTL = 900;			// 15 minutes
	const DEFAULT_LOCK_LIMIT = 10;		// 10 seconds
	const LOCK_PREFIX = "-locks-:";

	/*
	=====================
	connect

	Connect to a cache instance.
	Returns true if the connection succeeds (or may succeed in the future).
	=====================
	*/
	//protected abstract function connect( $options = null );
	public abstract function __construct( $options = null );

	/*
	=====================
	getRaw
	Looks up a key in the cache.  Return the key or `false` if not found.
	=====================
	*/
	protected abstract function getRaw( $key );

	/*
	=====================
	setRaw
	Sets a key in the cache.
	=====================
	*/
	protected abstract function setRaw( $key, $value, $ttl );

	/*
	=====================
	setRawExclusive
	Sets a key in the cache if it does not already exist. Returns `true` if the 
	key was created, otherwise `false`./
	=====================
	*/
	protected abstract function setRawExclusive( $key, $value, $ttl );

	/*
	=====================
	removeRaw
	Remove or invalidate a key in the cache.
	=====================
	*/
	protected abstract function removeRaw( $key );

	/*
	=====================
	removeAll
	Remove all keys from the cache
	=====================
	*/
	protected abstract function removeAll();

	/*
	=====================
	get
	Gets a key from the cache.
	=====================
	*/
	public function get( $key )
	{
		$key = (string) $key;
		$got = $this->getRaw( $key );
		return $got ? $got->v : false;
	}

	/*
	=====================
	getx
	Gets a key from the cache, with state feedback.
	$ttl will be set to the expected remaining lifetime of the key, in seconds.
	$ttl will be set to `false` if the key did not exist.
	=====================
	*/
	public function getx( $key, &$ttl )
	{
		$key = (string) $key;
		$got = $this->getRaw( $key );
		if (!$got) {
			return false;
		}
		$ttl = $got->e - microtime(true);
		return $got->v;
	}

	/*
	=====================
	set
	Sets a key in the cache.
	If $ttl is empty or less than zero, the cache entry is not created.
	=====================
	*/
	public function set( $key, $value, $ttl = null )
	{
		$key = (string) $key;
		if ($ttl === null) {
			$ttl = self::DEFAULT_TTL;
		}
		if ($ttl && $ttl > 0) {
			if ($ttl > 1000000000) {
				$expires = $ttl;
				$ttl = $ttl - microtime(true);
			} else {
				$expires = microtime(true) + $ttl;
			}
			$this->setRaw( $key, (object)[
				"v" => $value,
				"e" => $expires
				], $ttl );
		}
	}

	/*
	=====================
	setx
	Sets a key in the cache.
	If $ttl is empty or less than zero, the cache entry is not created.
	=====================
	*/
	public function setx( $key, $value, $ttl = null )
	{
		$key = (string) $key;
		if ($ttl === null) {
			$ttl = self::DEFAULT_TTL;
		}
		if ($ttl && $ttl > 0) {
			if ($ttl > 1000000000) {
				$expires = $ttl;
				$ttl = $ttl - microtime(true);
			} else {
				$expires = microtime(true) + $ttl;
			}
			$this->setRawExclusive( $key, (object)[
				"v" => $value,
				"e" => $expires
				], $ttl );
		}
	}

	/*
	=====================
	remove
	Remove (or invalidate) a key from the cache.
	=====================
	*/
	public function remove( $key )
	{
		$this->removeRaw( (string) $key );
	}

	/*
	=====================
	wait

	Wait for a valid value to appear in the cache.

	If `intervalms` is not supplied or is set to "auto", polling starts at 
	1ms and increases up to a maximum interval of 100ms.
	=====================
	*/
	public function wait( $key, $seconds, $intervalms = null )
	{
		$key = (string)$key;
		$auto = true;
		$usdelay = 1000;
		if ($intervalms !== null && $intervalms !== "auto") {
			$usdelay = $intervalms * 1000;
			$auto = false;
		}
		$now = microtime( true );
		$until = $now + $seconds;
		for (;;) {
			$entry = $this->getRaw( $key );
			if ($entry) {
				return $entry->v;
			} else if (microtime( true ) > $until) {
				return false;
			}
			usleep( $usdelay );
			// walk up to ~100ms delay slices
			if ($auto && $usdelay < 100000) {
				$usdelay *= 2;
			}
		}
	}

	/*
	=====================
	lock

	Get and lock a cached value. Locking is cooperative, and is intended mostly 
	for stampede control and other pathological update conditions.
	The locks are stored in the cache, as the original key with a 
	"-locks-:" prefix.

	If `threshold` is not null, the key will only be locked if it is within
	`threshold` seconds of expiring. If `threshold` is 0, the item will only be
	locked if it does not exist or has already expired.

	All locks expire automatically. If a `limit` is not supplied, the default 
	limit is 10 seconds. If `limit` is set to zero, the entry will be unlocked
	before this function returns, but the return value will still indicate 
	whether it could have been successfully locked or not.

	An approach for stampede control:

		// If we're within a minute of needing a new one (or it isn't ready), 
		// take a few seconds to build it now.
		if ($cache->lock( $key, 0, $got, 60, 5 )) {
			$got = doRebuildResource( $key );
			$cache->set( $key, $got );
			$cache->unlock( $key );
		} else if (!$got && !($got = $cache->wait( $key, 5 )) {
			// The other locker failed or is taking too long. 
			// Ask the user to try again?
			return;
		}
		// got a good (or good enough) value
		// ...


	=====================
	*/
	public function lock( $key, $wait = null, &$value = null, 
		$threshold = null, $limit = null )
	{
		$key = (string)$key;
		$now = microtime( true );
		if ($limit === null) {
			$limit = self::DEFAULT_LOCK_LIMIT;
		}
		$until = $now + ($wait ? $wait : 0);
		$usdelay = 1000;		// start at 1ms
		for (;;) {
			$entry = null;
			if ($threshold !== null) {
				$entry = $this->getRaw( $key );
				if ($entry && (!$threshold || $entry->e > $now + $threshold)) {
					$value = $entry->v;
					return false;
				}
			}
			if ($limit == 0) {
				$locked = !$this->getRaw( self::LOCK_PREFIX . $key );
			} else {
				$locked = $this->setRawExclusive( self::LOCK_PREFIX . $key, 
					(object)[ "v" => true, "e" => microtime( true ) + $limit ], 
					$limit );
			}
			if ($locked || $now >= $until) {
				$entry = $entry ? $entry : $this->getRaw( $key );
				if ($got) {
					$value = $got->v;
				} else {
					$value = false;
				}
				return !$locked;
			}
			usleep( $usdelay );
			// walk up to ~100ms delay slices
			if ($usdelay < 100000) {
				$usdelay *= 2;
			}
			$now = microtime( true );
		}
	}

	/*
	=====================
	unlock

	Removes a lock on a key
	=====================
	*/
	public function unlock( $key )
	{
		$this->removeRaw( self::LOCK_PREFIX . $key );
	}
}


