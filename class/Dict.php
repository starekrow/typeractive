<?php /* Copyright (C) 2017 David O'Riva. MIT License.
       ********************************************************/
namespace Typeractive;

/*
================================================================================

Dict

A fast and flexible dictionary type. 
  * Access by `->property` or `[index]` notation
  * Reads missing keys as `null` with no warnings
  * Iterable with foreach()
  * Converts back to object or array, optionally with recursion

================================================================================
*/

class Dict implements 
	\ArrayAccess, \Countable, \IteratorAggregate, \JsonSerializable
{
	protected $data;

	/*
	=====================
	ToObject
	=====================
	*/
	public function ToObject( $deep = false )
	{
		$res = (object) $this->data;
		if (is_int( $deep ))  $deep = ($deep > 1) ? $deep - 1 : 0;
		if ($deep) {
			foreach ($res as $k => &$v) {
				if ($v instanceof Dict) {
					$v = $v->ToObject( $deep );
				}
			}
		}
		return $res;
	}

	/*
	=====================
	ToArray
	=====================
	*/
	public function ToArray( $deep = false )
	{
		$res = $this->data;
		if (is_int( $deep ))  $deep = ($deep > 1) ? $deep - 1 : 0;
		if ($deep) {
			foreach ($res as $k => &$v) {
				if ($v instanceof Dict) {
					$v = $v->ToArray( $deep );
				}
			}
		}
		return $res;
	}

	/*
	=====================
	HasKey
	=====================
	*/
	public function HasKey( $key )
	{
		return array_key_exists( $key, $this->data );
	}

	/*
	=====================
	Merge
	=====================
	*/
	public function Merge( $other )
	{
		if (!$other) {
			return;
		}
		foreach ($other as $k => $v) {
			$this->data[ $k ] = $v;
		}
	}


	/*
	=====================
	__construct
	=====================
	*/
	public function __construct( $ob = null, $deep = false )
	{
		if (is_array( $ob )) {
			$this->data = $ob;
		} else if (is_object( $ob )) {
			if ($ob instanceof Dict) {
				$this->data = $ob->ToArray();
			} else if ($ob instanceof \stdClass) {
				$this->data = (array) $ob;
			} else {
				throw new \Exception( 
					"Cannot initialize a dict with that object" );
			}
		} else if (is_null( $ob )) {
			$this->data = [];
		} else {
			throw new \Exception( "Cannot initialize a dict with that value" );
		}
		if (is_int( $deep ))  $deep = ($deep > 1) ? $deep - 1 : 0;
		if ($deep) {
			foreach( $this->data as $k => &$v ) {
				if (   (is_array( $v ) && $this->is_assoc( $v ))
					|| (is_object( $v ) && $ob instanceof \stdClass)
				   ) {
					$v = new Dict( $v );
				}
			}
		}
	}

	/*
	=====================
	is_assoc
	=====================
	*/
	protected function is_assoc($arr)
	{
	    if (!is_array( $arr ) || array() === $arr) {
	    	return false;
	    }
	    return array_keys($arr) !== range(0, count($arr) - 1);
	}

	/*
	----------------------------------------------------------------------------
	ArrayAccess interface
	----------------------------------------------------------------------------
	*/
	public function offsetSet( $offset, $value )
	{
		if( !is_null( $offset ) ) {
			$this->data[ $offset ] = $value;
		}
	}
	public function offsetExists( $offset )
	{
		return array_key_exists( $offset, $this->data );
	}
	public function offsetUnset( $offset )
	{
		unset( $this->data[ $offset ] );
	}
	public function offsetGet( $offset )
	{
		return array_key_exists( $offset, $this->data ) ? 
			$this->data[ $offset ] : null;
	}

	/*
	----------------------------------------------------------------------------
	Object property access overloads
	----------------------------------------------------------------------------
	*/
	public function __set( $offset, $value )
	{
		if( !is_null( $offset ) ) {
			$this->data[ $offset ] = $value;
		}
	}
	public function __isset( $offset )
	{
		return isset( $this->data[ $offset ] );
	}
	public function __unset( $offset )
	{
		unset( $this->data[ $offset ] );
	}
	public function __get( $offset )
	{
		return array_key_exists( $offset, $this->data ) ? 
			$this->data[ $offset ] : null;	
	}

	/*
	----------------------------------------------------------------------------
	Countable interface
	----------------------------------------------------------------------------
	*/
	public function count()
	{
		return count( $this->data );
	}

	/*
	----------------------------------------------------------------------------
	IteratorAggregate interface
	----------------------------------------------------------------------------
	*/
    public function getIterator()
    {
        return new \ArrayIterator( $this->data );
    }

	/*
	----------------------------------------------------------------------------
	JsonSerializable interface
	----------------------------------------------------------------------------
	*/
    public function jsonSerialize()
    {
        return $this->ToObject( true );
    }

}
