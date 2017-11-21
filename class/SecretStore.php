<?php /* Copyright (C) 2017 David O'Riva. All rights reserved.
       ********************************************************/

namespace Typeractive;

/*
================================================================================
SecretStore - Manage a storehouse of secret values

Given a directory, manages a set of data keys and secret values. The following
operations are supported:

  * Put - saves a secret value
  * Get - loads a secret value
  * Remove - removes a secret value
  * TODO: RotateDataKey - replace the data key with a new one
  * TODO: ChangeMasterKey - re-encodes the data key with a new master key
  * Open - opens the secret store
  * Close - closes the secret store
  * Reset - creates or overwrites the secret store

================================================================================
*/
class SecretStore
{
	public $path;
	public $error;
	//protected $masterKey;
	protected $activeDataKey;
	protected $dataKeys;
	protected $secrets;
	
	/*
	=====================
	LoadDataKeys
	=====================
	*/
	protected function LoadDataKeys( $masterKey )
	{
		$dk = Secret::Import( file_get_contents("$this->path/data.key") );
		if (!$dk) {
			error_log( "Failed data key import in Secret" );
			return false;
		}
		if (!$dk->Unlock( $masterKey )) {
			var_dump( $masterKey );
			error_log( "Failed data key unlock in Secret" );
			return false;
		}
		$this->dataKeys = [];
		foreach ($dk->value as $id => $key) {
			if ($id == "active") {
				$this->activeDataKey = $key;
			} else {
				$this->dataKeys[ $id ] = CryptoKey::Import($key);
			}
		}
		return true;
	}

	/*
	=====================
	SaveDataKeys
	=====================
	*/
	protected function SaveDataKeys( $masterKey )
	{
		$kl = [];
		foreach ($this->dataKeys as $id => $key) {
			$kl[ $id ] = $key->Export();
		}
		$kl[ "active" ] = $this->activeDataKey;
		$dk = new Secret( $kl );
		$dk->AddLock( $masterKey );
		file_put_contents("$this->path/data.key", $dk->Export());
		return true;
	}

	/*
	=====================
	RotateDataKey

	Process is intended to eliminate possibility of catastrophic secret loss.
	  * Add new data key to data key store
	  * Cycle through all secrets, unlocking with existing data key and 
	    then adding lock for new data key.
	  * Remove old data key from key store
	  * Any interruption of the process will leave the key store and secrets
	    in a stable, recoverable state.
	  * If there are multiple data keys when the process starts, all existing
	    keys are tried for unlocking the secrets and then retired at the end. 
	=====================
	*/
	public function RotateDataKey()
	{

	}


	/*
	=====================
	Get
	=====================
	*/
	public function Get( $name )
	{
		if (empty( $this->secrets[ $name ] )) {
			$sf = @file_get_contents( "$this->path/$name.info" );
			if (!$sf) {
				return false;
			}
			$s = Secret::Import( $sf );
			if (!$s) {
				return false;
			}
			$this->secrets[ $name ] = $s;
		}
		$s = $this->secrets[ $name ];
		if (!$s->locked) {
			return $s->value;
		}
		foreach ($this->dataKeys as $key) {
			if ($s->Unlock( $key )) {
				break;
			}
		}
		return $s->locked ? false : $s->value;
	}

	/*
	=====================
	Has
	=====================
	*/
	public function Has( $name )
	{
		return file_exists( "$this->path/$name.info" );
	}

	/*
	=====================
	Put
	=====================
	*/
	public function Put( $name, $value )
	{
		if (!empty( $this->secrets[ $name ] )
		    && !$this->secrets[ $name ]->locked) {
			$did = $this->secrets[ $name ]->Update( $value );
			if (!$did) {
				error_log( "Failed secret update" );
			}
		} else {
			$this->secrets[ $name ] = new Secret( $value );			
		}
		$this->secrets[ $name ]->AddLock( 
			$this->dataKeys[ $this->activeDataKey ] );
		$kd = $this->secrets[ $name ]->Export();
		file_put_contents( "$this->path/$name.info", $kd );
	}

	/*
	=====================
	Remove
	=====================
	*/
	public function Remove( $name )
	{
		unset( $this->secrets[ $name ] );
		unlink( "$this->path/$name.info" );
	}

	/*
	=====================
	Reset

	Creates a new store with a fresh data key.
	This will ***DESTROY*** any existing secrets by erasing all known data keys.
	It is unlikely that you will want to do this except when setting up a new
	store.
	=====================
	*/
	public function Reset( $masterKey )
	{
		$dk = new CryptoKey();

		$this->dataKeys = [];
		$this->dataKeys[ $dk->id ] = $dk;
		$this->activeDataKey = $dk->id;
		$this->secrets = [];

		$mk = new CryptoKey( $masterKey, "master" );

		@mkdir( $this->path, 0700, true );
		return $this->SaveDataKeys( $mk );
	}

	/*
	=====================
	Exists
	=====================
	*/
	public function Exists()
	{
		return file_exists( "$this->path/data.key" );
	}

	/*
	=====================
	Open

	Opens the store using the given master key.
	=====================
	*/
	public function Open( $masterKey )
	{
		$mk = new CryptoKey( $masterKey, "master" );
		$did = $this->LoadDataKeys( $mk );
		if ($did) {
			return true;
		}
		return false;
	}

	/*
	=====================
	Close

	Closes the store and forgets all keys and secrets.
	=====================
	*/
	public function Close()
	{
		$this->dataKeys = [];
		$this->secrets = [];
	}

	/*
	=====================
	__construct

	Sets up a SecretStore instance to operate at the given location. This does
	not actually create or open any files. Use Open() or Reset() as appropriate
	to start using the store.
	=====================
	*/
	public function __construct( $path )
	{
		$this->path = $path;
		$this->dataKeys = [];
		$this->secrets = [];
	}
}
