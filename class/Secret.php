<?php /* Copyright (C) 2017 David O'Riva. All rights reserved.
       ********************************************************/
namespace Typeractive;

/*
================================================================================
Secret - Management for secret values with multiple lockboxes

When a secret is created, a new internal key is generated to encrypt it. You 
never work with this key directly. Instead, you add your own keys to the 
secret. Each key you add will create a corresponding "lock" that contains the
internal key. This can be used to support a number of traditionally difficult 
features, like non-atomic key rotation and separation of key management from 
secret value access.
================================================================================
*/
class Secret
{
	/* `locked` - whether the secret is currently locked. It's read only.
	   Don't make me stick it behind a getter. */
	public $locked;
	public $value;
	protected $locks;
	protected $key;
	protected $ciphertext;
	
	/*
	=====================
	Unlock - Attempt to unlock the secret with a CryptoKey
	=====================
	*/
	public function Unlock( $key = null )
	{
		if ($key) {
			if (empty( $this->locks[ $key->id ] )) {
				return false;
			}
			$got = $key->Unlock( $this->locks[ $key->id ] );
			$ikey = CryptoKey::Import( $got );

			if (!$ikey) {
				return false;
			}
			if (!$this->locked) {
				return true;
			}
			$value = $ikey->Unlock( $this->ciphertext );
			if ($value === false) {
				return false;
			}
			$this->key = $ikey;
			if ($value[0] == "s") {
				$this->value = substr( $value, 1 );
			} else if ($value[0] == "p") {
				$this->value = unserialize( substr( $value, 1 ) );	
			}
			$this->locked = false;
			return true;
		}
		if (!$this->locked) {
			return true;
		}
		return true;
	}	
	/*
	=====================
	Lock - Lock the secret by erasing the internal key and value
	=====================
	*/
	public function Lock()
	{
		if ($this->locked) {
			return true;
		}
		unset( $this->key );
		unset( $this->value );
		return true;
	}
	/*
	=====================
	Update

	Update the secret value. The secret must be unlocked.

	Returns `true` if the value was updated, otherwise `false`.
	=====================
	*/
	public function Update( $value )
	{
		if ($this->locked) {
			error_log( "still locked" );
			return false;
		}
		if (is_object( $value )) {
			$value = (object)( (array) $value );
		}
		$this->value = $value;
		if (is_string( $this->value )) {
			$plaintext = "s" . $value;
		} else {
			// Shouldn't use JSON unless strings are guaranteed UTF-8
			$plaintext = "p" . serialize( $value );
		}
		$this->ciphertext = $this->key->Lock( $plaintext );
		return true;
	}

	/*
	=====================
	AddLock

	Adds a lock to the secret. The secret must be unlocked for this to succeed.
	=====================
	*/
	public function AddLock( $key )
	{
		$this->locks[ $key->id ] = $key->Lock( $this->key->Export() );
		return true;
	}


	/*
	=====================
	RemoveLock

	Removes a lock from the secret. Works whether the secret is unlocked or 
	not. 

	Returns `true` if the lock was found and removed, otherwise `false`.
	=====================
	*/
	public function RemoveLock( $id )
	{
		if (!empty( $this->locks[ $id ] )) {
			unset( $this->locks[ $id ] );
			return true;
		}
		return false;
	}

	/*
	=====================
	ListLocks

	Returns an array containing a list of all locks present on the secret.
	=====================
	*/
	public function ListLocks()
	{
		return array_keys( $this->locks );
	}

	/*
	=====================
	HasLock

	Returns `true` if the given lock is present on the secret, otherwise 
	`false`.
	=====================
	*/
	public function HasLock( $id )
	{
		return !empty( $this->locks[ $id ] );
	}

	/*
	=====================
	Export

	Returns a string containing a "safe" representation of the secret.
	=====================
	*/
	public function Export()
	{
		return json_encode( [
			 "locks" => $this->locks
			,"data" => $this->ciphertext
		], JSON_PRETTY_PRINT );
	}
	/*
	=====================
	Import (static)

	Returns a secret built from the given (previously exported) string.
	Returns `false` if the data cannot be imported.
	=====================
	*/
	public static function Import( $data )
	{
		$data = json_decode( $data );
		return new Secret( null, $_import = $data );
	}
	/*
	=====================
	__construct

	Builds a new secret from a value.
	=====================
	*/
	public function __construct( $value, $_import = null )
	{
		if ($_import) {
			$this->locks = (array) $_import->locks;
			$this->ciphertext = $_import->data;
			$this->locked = true;
			return;
		}
		$this->locked = false;
		$this->locks = [];
		$this->key = new CryptoKey();
		$this->Update( $value );
	}
}
