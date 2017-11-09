<?php /* Copyright (C) 2017 David O'Riva. All rights reserved.
       ********************************************************/
namespace Typeractive;

/*
================================================================================
CryptoKey - AES Encyption
================================================================================
*/
class CryptoKey
{
	// `id` - a string identifying this key. Freely modifiable, but must
	// only use chars in [-+_=/a-zA-Z0-9]
	public $id;
	// `cipher` - cipher to use. Modify at your own risk
	public $cipher = "AES-128-CBC";
	// `data` - key data. Not normally accessible.
	protected $data;

	/*
	=====================
	RandomGUID (static) - Generate a random GUID
	=====================
	*/
	public static function RandomGUID()
	{
		$data = openssl_random_pseudo_bytes(16);
		$data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
		$data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
		return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
	}

	/*
	=====================
	Unlock - Decrypt ciphertext with this key
	Returns the decrypted message, or `false` if the key didn't work.
	=====================
	*/
	public function Unlock( $ciphertext )
	{
		$sha2len = 32;
		$options = OPENSSL_RAW_DATA;
		$ivlen = openssl_cipher_iv_length( $this->cipher );
		$c = base64_decode( $ciphertext );
		$iv = substr( $c, 0, $ivlen );
		$hmac = substr( $c, $ivlen, $sha2len );
		$ciphertext_raw = substr( $c, $ivlen + $sha2len );
		$plaintext = openssl_decrypt( $ciphertext_raw, 
			$this->cipher, $this->data, $options, $iv );
		$calcmac = hash_hmac( 'sha256', $ciphertext_raw, $this->data, 
			$as_binary = true );
		$res = 0;

		for ($i = 0; $i < strlen( $hmac ); ++$i) {
			$res |= (($hmac[$i] != $calcmac[ $i ]) ? 1 : 0);
		}
		return $res ? false : $plaintext;
	}

	/*
	=====================
	Lock - Encrypt a message with this key.
	Returns printable ciphertext for the message, or `false` if the key is 
	invalid or encryption failed.
	=====================
	*/
	public function Lock( $message )
	{
		if (!$this->data) {
			return false;
		}
		$options = OPENSSL_RAW_DATA;
		$ivlen = openssl_cipher_iv_length( $this->cipher );
		$iv = openssl_random_pseudo_bytes( $ivlen );
		$ciphertext_raw = openssl_encrypt( $message, $this->cipher, $this->data, 
			$options, $iv );
		//echo "ct: "; var_dump( $ciphertext_raw );
		$hmac = hash_hmac( 'sha256', $ciphertext_raw, $this->data, 
			$as_binary = true );
		$ciphertext = base64_encode( $iv.$hmac.$ciphertext_raw );
		return $ciphertext;
	}

	/*
	=====================
	Export

	Returns a string containing a representation of the key, with the ID and
	cipher in use. This is probably sensitive information.
	=====================
	*/
	public function Export()
	{
		$id = $this->id;
		$cp = base64_encode( $this->cipher );
		$kd = base64_encode( $this->data );
		return "k0|$id|$cp|$kd";
	}
	/*
	=====================
	Import (static)

	Returns a CryptoKey built from the given (previously exported) string.
	Returns `false` if the key cannot be imported.
	=====================
	*/
	public static function Import( $data )
	{
		if (!is_string( $data ))  return false;
		$kp = explode( "|", $data );
		if (count( $kp ) != 4 || $kp[0] != "k0")  return false;
		$dat = base64_decode( $kp[3] );
		$id = $kp[1];
		if (!$dat) {
			return false;
		}
		return new CryptoKey( $dat, $id, base64_decode( $kp[2] ) );
	}

	/*
	=====================
	__construct

	Sets up the key.
	  * `data` - A binary string containing key data. If `null`, a new 
	    256-bit random key is generated.
	  * `id` - A string identifying this key. May only contain characters
	    from the set:
	    	a-z A-Z 0-9 / = - + _ 
	  	May be read through the `id` property of the object. 
	  	If `null`, a new 128-bit random id is generated.
	=====================
	*/
	public function __construct( $data = null, $id = null, $cipher = null )
	{
		$this->data = $data;
		if (!$data) {
			$this->data = openssl_random_pseudo_bytes( 32 );
		}
		if ($id !== null) {
			$this->id = $id;
		} else {
			$this->id = self::RandomGUID();
		}
		if ($cipher) {
			$this->cipher = $cipher;
		}
	}
}

