<?php /* Copyright (C) 2017 David O'Riva. MIT License.
       ********************************************************/
namespace Typeractive;

/*
================================================================================

UserData

User record handling

================================================================================
*/

class UserData
{
	public $id;
	protected $record;

	public static $tableDef = [
		"autoindex" => "userid"
	];

	/*
	=====================
	__construct
	=====================
	*/
	protected function __construct( $record )
	{
		$this->record = $record;
		$this->id = $record->userid;
	}

	/*
	=====================
	GetName
	=====================
	*/
	public function GetName()
	{
		return $this->record->name;
	}

	/*
	=====================
	CheckPassword
	=====================
	*/
	public function CheckPassword( $password )
	{
		$password = trim( $password );
		$auth = new SqlShadow( "auth" );
		$auth->userid = $this->id;
		$auth->method = "username";
		if (!$auth->Load()) {
			return false;
		}
		if (!$auth->token1) {
			return false;
		}
		return self::VerifyPassword( $password, $auth->token1 );
	}

	/*
	=====================
	EmailInUse
	=====================
	*/
	public static function EmailInUse( $email )
	{
		$ar = new SqlShadow( "auth" );
		$ar->method = "email";
		$ar->methodkey = self::NormalizeEmail( $email );
		if ($ar->Load()) {
			return true;
		}
		return false;
	}

	/*
	=====================
	UsernameInUse
	=====================
	*/
	public static function UsernameInUse( $name )
	{
		$ar = new SqlShadow( "auth" );
		$ar->method = "username";
		$ar->methodkey = self::NormalizeUsername( $name );
		if ($ar->Load()) {
			return true;
		}
		return false;
	}

	/*
	=====================
	LookupUsername

	Looks up a user by username.
	Returns a User instance or `false` if the user doesn't exist.
	=====================
	*/
	public static function LookupUsername( $name )
	{
		$ar = new SqlShadow( "auth" );
		$ar->method = "username";
		$ar->methodkey = self::NormalizeUsername( $name );
		$user = false;
		if ($ar->Load()) {
			$user = self::Open( $ar->userid );
		}
		return $user;
	}

	/*
	=====================
	LookupEmail

	Looks up a user by email address.
	Returns a User instance or `false` if the user doesn't exist.
	=====================
	*/
	public static function LookupEmail( $email )
	{
		$ar = new SqlShadow( "auth" );
		$ar->method = "email";
		$ar->methodkey = self::NormalizeEmail( $email );
		$user = false;
		if ($ar->Load()) {
			$user = self::Open( $ar->userid );			
		}
		return $user;
	}


	/*
	=====================
	NormalizeEmail
	=====================
	*/
	public static function NormalizeEmail( $email )
	{
		$email = trim( $email );
		$ep = explode( "@", $email, 2 );
		// TODO: do this right: nameprep, comments etc.
		return implode( "@", [ $ep[0], strtolower( $ep[1] ) ] );
	}

	/*
	=====================
	NormalizeUsername
	Calculates the canonical username from a display or hand-entered name.
	Normalized names are:
		* trimmed
		* all lower-case
		* all punctuation replaced with '-'
	Returns false if the name is not valid.
	=====================
	*/
	public static function NormalizeUsername( $name )
	{
		$name = trim( $name );
		if (!self::ValidateUsername( $name )) {
			return false;
		}
		$xn = strtolower( $name );
		$xn = str_replace( ["-","_","."," "], ["-","-","-","-"], $xn );
		return $xn;
	}

	/*
	=====================
	ValidateUsername
	=====================
	*/
	public static function ValidateUsername( $username )
	{
		if (trim( $username ) !== $username) {
			return false;
		}
		if (!preg_match( "/^[a-zA-Z][-_ a-zA-Z0-9]{2,30}$/", $username )) {
			return false;
		}
		return true;
	}

	/*
	=====================
	ValidatePassword
	=====================
	*/
	public static function ValidatePassword( $password )
	{
		if (trim( $password ) !== $password) {
			return false;
		}
		if (!preg_match( "/^[\\P{C} ]{8,64}$/u", $password )) {
			return false;
		}
		return true;
	}

	/*
	=====================
	HashPassword
	=====================
	*/
	public static function HashPassword( $password )
	{
		$pw = password_hash( $pw, PASSWORD_BCRYPT, [ "cost" => 12 ] );
	}

	/*
	=====================
	VerifyPassword
	=====================
	*/
	public static function VerifyPassword( $password, $hash )
	{
		return password_verify( $password, $hash );
	}

	/*
	=====================
	Create

	Creates a new user. The name must be unique.
	Returns a User instance or `false` if the creation failed.
	=====================
	*/
	public static function Create( $name, $password = null )
	{
		// First, try to enter the authentication record. This will tell us
		// whether the username is available.
		$name = trim( $name );
		$xn = self::NormalizeUsername( $name );
		$ar = new SqlShadow( "auth" );
		$ar->method = "username";
		$ar->methodkey = $xn;

		$pwhash = null;
		if (isset( $password )) {
			$pwhash = self::HashPassword( $password );
		}

		$ar->token1 = $pwhash;
		$ar->token2 = $name;
		if (!$ar->Flush()) {
			return false;
		}
		$rec = new SqlShadow( "users" );
		$rec->name = $name;
		if (!$rec->Flush()) {
			$ar->Delete();
			return false;
			//throw new Exception( "unable to create user" );
		}
		$ar->userid = $rec->userid;
		$ar->Flush();
		return new UserData( $rec );
	}

	/*
	=====================
	Open

	Gets a User instance for the given id.
	=====================
	*/
	public static function Open( $userid )
	{
		$rec = new SqlShadow( "users", [ "userid" => $userid ] );
		if (!$rec->Load()) {
			throw new \Exception( "unable to load user $userid" );
		}
		return new UserData( $rec );
	}
}
