<?php /* Copyright (C) 2017 David O'Riva. MIT License.
       ********************************************************/
namespace Typeractive\Cache;

/*
================================================================================

memcached - Cache driver for memcached

================================================================================
*/
class d_memcached extends CacheDriver
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
		$host = "localhost";
		$port = 11211;
		if ($options) {
			foreach ($options as $k => $v) {
				switch ($k) {
				case "host":
					$host = $v;
					break;
				case "port":
					$port = $v;
					break;
				}
			}
		}
		try {
			if (!class_exists( "\\Memcached" )) {
				return false;
			}
			$this->mc = new \Memcached();
			// connections are cached outside the VM in some configurations
			$servers = $this->mc->getServerList();
			$already = false;
			if (is_array($servers)) {
				foreach ($servers as $server) {
					if ($server['host'] == $host and $server['port'] == $port) {
						$already = true;
					}

				}
			}
			if (!$already) {
				$this->mc->addServer( $host, $port );
			}
		} catch (Exception $e) {
			return false;
		}
		return true;
	}

	/*
	=====================
	getRaw
	Looks up a key in the cache.  Return the key or `false` if not found.
	=====================
	*/
	protected function getRaw( $key )
	{
		return $this->mc->get( $key );
	}

	/*
	=====================
	setRaw
	Sets a key in the cache.
	=====================
	*/
	protected function setRaw( $key, $value, $ttl )
	{
		return $this->mc->set( $key, $value, (int)$ttl );
	}

	/*
	=====================
	setRawExclusive
	Sets a key in the cache if it does not already exist. Returns `true` if the 
	key was created, otherwise `false`./
	=====================
	*/
	protected function setRawExclusive( $key, $value, $ttl )
	{
		return $this->mc->add( $key, $value, (int)$ttl );
	}

	/*
	=====================
	removeRaw
	Remove or invalidate a key in the cache.
	=====================
	*/
	protected function removeRaw( $key )
	{
		return $this->mc->delete( $key );
	}

	/*
	=====================
	removeAll
	Remove all keys from the cache
	=====================
	*/
	protected function removeAll()
	{
		return $this->mc->flush();
	}

}


