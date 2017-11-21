<?php /* Copyright (C) 2017 David O'Riva. MIT License.
       ********************************************************/
namespace Typeractive\Cache;

/*
================================================================================

CacheDriver - caching subsystem abstraction

Provides a high-level cache interface and defines an abstract low-level
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

	@return boolean `true` if the connection succeeds (or is thought likely
	                to succeed).
	=====================
	*/
	public abstract function connect( $options = null );

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
	key was created, otherwise `false`.
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

	Sets a value for a key in the cache.

	The expiration time may be set relative to the current time or as an
	absolute UTC timestamp. If the value given for $ttl is less than 
	1,000,000,000 it will be handled as a number of seconds from the current
	time to wait before expiring the entry. Otherwise, it represents a UTC
	time at which the value should expire. If $ttl less than or equal to zero, 
	the cache entry is not created. If $ttl is null, the default expiration 
	time of 15 minutes is used.

	@param string $key    Key to set
	@param mixed  $value  Value to assign to the key
	@param number $ttl    Expiration time (see description)

	@return void
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

	Sets a value for key in the cache, but only if an unexpired value does not 
	already exist for that key.

	See `set` for a discussion of expiration time values.

	@param string $key    Key to set
	@param mixed  $value  Value to assign to the key
	@param number $ttl    Expiration time (see description)

	@return boolean Whether the value was set.
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
			return $this->setRawExclusive( $key, (object)[
				"v" => $value,
				"e" => $expires
				], $ttl );
		}
		return false;
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

	Wait for a value to appear in the cache.

	If `intervalms` is not supplied or is set to "auto", polling starts at 
	1ms and increases up to a maximum interval of 100ms.

	@param string     $key         Cache key to load
	@param number     $seconds     How long to wait for a value
	@param number     $intervalms  Polling interval in milliseconds, or "auto"

	@return mixed Cached value, or `false` if none found
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
	for stampede control and other pathological update conditions. The locks 
	are stored in the cache, as the original key with a "-locks-:" prefix.

	If $threshold is not `null`, the key will only be locked if it is within
	$threshold seconds of expiring. If $threshold is 0, the item will only be
	locked if it does not exist or has already expired.

	All locks expire automatically. If $limit is `null`, the default 
	limit is 10 seconds. If $limit is set to 0, the entry will be unlocked
	before this function returns, but the return value will still indicate 
	whether it could have been successfully locked or not.

	An approach for stampede control:

		// If we're within a minute of needing a new one (or it isn't ready), 
		// take a few seconds to build it now.
		if (!$cache->lock( $key, 0, $value, 60, 5 )) {
			// Didn't get the lock, but we might have a value.
			// If we didn't get a value, wait a bit for one to appear
			$value = $value ? $value : $cache->wait( $key, 5 );
			if (!$value) {
				// The other locker failed or is taking too long. 
				// Ask the user to try again?
				return false;
			}
		} else {
			$value = doRebuildResource( ... );
			$cache->set( $key, $value );
			$cache->unlock( $key );
		}
		// got a good (or good enough) $value
		doStuffWith( $value );


	@param string   $key       Key to lock
	@param mixed    $wait      How long to wait to acquire the lock
	@param mixed    $value     Value found in key, or `false`
	@param mixed    $threshold Lock if within this time of expiry
	@param mixed    $limit     Lock for this long

	@return boolean Whether a lock was acquired

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
				if ($entry) {
					$value = $entry->v;
				} else {
					$value = false;
				}
				return $locked;
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


