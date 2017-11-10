<?php /* Copyright (C) 2017 David O'Riva. MIT License.
       ********************************************************/
namespace Typeractive;

/*
================================================================================

User

Represents a user.

================================================================================
*/

class User
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
		$this->id = $record->blogid;
	}

	/*
	=====================
	RenderPage

	Outputs an HTML page for the given blog entry	
	=====================
	*/
	public function RenderPage( $id )
	{
		$md = new \Parsedown();

		$text = file_get_contents( "res/b$id.md" );
		$blog = $md->text( $text );

		$text = file_get_contents( "res/qbio.md" );
		$bio = $md->text( $text );

		$out = str_replace( [
				"{{header}}",
				"{{css}}",
				"{{blog}}",
				"{{bio}}",
				"{{toc}}"
			], [
				file_get_contents( "res/header.html" ),
				file_get_contents( "res/blog.css" ),
				$blog,
				$bio,
				file_get_contents( "res/toc.html" )
			], 
			file_get_contents( "res/frame.html" )
		 );
		echo $out;
	}


	/*
	=====================
	SetAuthor
	=====================
	*/
	public function SetAuthor( $authorid )
	{
		$this->record->authorid = $authorid;
	}

	/*
	=====================
	SetBiography
	=====================
	*/
	public function SetBiography( $text )
	{
		// TODO: safety checks
		$t = new TextRecord( $this->record->biotext );
		$this->record->biotext = $t->textid;
		$t->SetText( $text );
	}

	/*
	=====================
	SetTitle
	=====================
	*/
	public function SetTitle( $title )
	{
		$t = new TextRecord( $this->record->titletext );
		$this->record->titletext = $t->textid;
		$t->SetText( $text );
	}

	/*
	=====================
	SetHeader
	=====================
	*/
	public function SetHeader( $text )
	{
		// TODO: safety checks
		$t = new TextRecord( $this->record->headertext );
		$this->record->headertext = $t->textid;
		$t->SetText( $text );
	}

	/*
	=====================
	SetDefaultPost
	=====================
	*/
	public function SetDefaultPost( $post )
	{
		if ($post === "latest") {
			$post = null;
		}
		$this->record->rootpost = $post;
	}

	/*
	=====================
	CanonicalUsername
	Calculates the canonical username from a display or hand-entered name.
	Canonical names are:
		* trimmed
		* all lower-case
		* all punctuation replaced with '-'
	Returns false if the name is not valid.
	=====================
	*/
	public static function CanonicalUsername( $name )
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
	Create

	Creates a new user. The name must be unique.
	Returns a User instance or `false` if the creation failed.
	=====================
	*/
	public static function Create( $name, $pwhash = null )
	{
		// First, try to enter the authentication record. This will tell us
		// whether the username is available.
		$name = trim( $name );
		$xn = self::CanonicalUsername( $name );
		$ar = new SqlShadow( "auth" );
		$ar->method = "username";
		$ar->methodkey = $xn;
		$ar->token1 = $pwhash;
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
		return new User( $rec );
	}

	/*
	=====================
	Open

	Gets a Blog instance for the given id.
	=====================
	*/
	public static function Open( $blogid )
	{
		$rec = new SqlShadow( "blogs", [ "blogid" => $blogid ] );
		if (!$rec->Load()) {
			throw new \Exception( "unable to load blog $blogidå" );
		}
		return new Blog( $rec );
	}
}
