<?php /* Copyright (C) 2017 David O'Riva. MIT License.
       ********************************************************/
namespace Typeractive;

// TODO: make these
// require_once( "polyfills/random_bytes.php" );
// require_once( "polyfills/hash_equals.php" );

/*
================================================================================

PostData

Represents a single post. Use factory methods to instantiate. 

================================================================================
*/

class PostData
{
	public $id;
	protected $record;
	protected $text;

	// how long an issued access key will last, in seconds.
	// This is used for such things as commenting on posts (so you can only
	// comment if you can prove that you've seen the post).

	const DEFAULT_ACCESS_TTL = 86400;

	/*
	=====================
	__construct
	=====================
	*/
	protected function __construct( $record )
	{
		$this->record = $record;
		$this->id = $record->postid;
		$this->text = (object)[];
	}

	/*
	=====================
	GetTextField
	=====================
	*/
	protected function GetTextField( $field )
	{
		if (!empty( $this->text->$field )) {
			return $this->text->$field->GetText();
		}
		if (!$this->record->$field) {
			return "";
		}
		// TODO: safety checks
		$t = TextData::Load( $this->record->$field );
		$this->text->$field = $t;
		return $t->GetText();
	}

	/*
	=====================
	SetTextField
	=====================
	*/
	protected function SetTextField( $field, $text )
	{
		// TODO: safety checks?
		if ($text == null) {
			$text = "";
		}
		if (!$this->record->$field) {
			$t = TextData::Create( 
				$text, 
				"v1:$field:markdown", 
				$this->record->authorid 
			);
			$this->text->$field = $t;
			$this->record->$field = $t->id;
		} else {
			// TODO: history
			if (empty( $this->text->$field )) {
				$this->GetTextField( $field );
			}
			$this->text->$field->SetText( $text );
			$this->text->$field->Save();
		}
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
	SetTitle
	=====================
	*/
	public function SetTitle( $text )
	{
		// TODO: safety checks?
		$this->SetTextField( "titleid", $text );
	}

	/*
	=====================
	SetDateline
	=====================
	*/
	public function SetDateline( $text )
	{
		// TODO: safety checks?
		$this->SetTextField( "datelineid", $text );
	}

	/*
	=====================
	SetText
	=====================
	*/
	public function SetText( $text )
	{
		// TODO: safety checks?
		$this->SetTextField( "textid", $text );
	}

	/*
	=====================
	SetDraft
	=====================
	*/
	public function SetDraft( $text )
	{
		// TODO: safety checks?
		$this->SetTextField( "draftid", $text );
	}


	/*
	=====================
	SetState
	=====================
	*/
	public function SetState( $state )
	{
		switch ($state) {
		case "draft":
			$this->record->published = 0;
			$this->record->deleted = 0;
			break;
		case "published":
			$this->record->published = 1;
			$this->record->deleted = 0;
			if (!$this->record->postdate) {
				$this->record->postdate = $this->record->DateTime();
			}
			break;
		case "deleted":
			$this->record->deleted = 1;
			break;
		case "undelete":
			$this->record->deleted = 0;
			break;
		default:
			throw new \Exception("Unknown post state: $state");
			break;
		}
	}

	/*
	=====================
	GetState
	=====================
	*/
	public function GetState()
	{
		if ($this->record->deleted) {
			return "deleted";
		}
		if ($this->record->published) {
			return "published";
		}
		return "draft";
	}

	/*
	=====================
	GetAuthor
	=====================
	*/
	public function GetAuthor()
	{
		return $this->record->authorid;
	}

	/*
	=====================
	GetTitle
	=====================
	*/
	public function GetTitle()
	{
		return $this->GetTextField( "titleid" );
	}


	/*
	=====================
	GetText
	=====================
	*/
	public function GetText()
	{
		return $this->GetTextField( "textid" );
	}

	/*
	=====================
	GetDraft
	=====================
	*/
	public function GetDraft()
	{
		return $this->GetTextField( "draftid" );
	}

	/*
	=====================
	GetDateline
	=====================
	*/
	public function GetDateline()
	{
		return $this->GetTextField( "datelineid" );
	}

	/*
	=====================
	GetBlogId
	=====================
	*/
	public function GetBlogId()
	{
		return $this->record->blogid;
	}

	/*
	=====================
	GetPostDate
	=====================
	*/
	public function GetPostDate()
	{
		return $this->record->postdate;
	}

	/*
	=====================
	GetPostTimestamp
	=====================
	*/
	public function GetPostTimestamp()
	{
		return $this->record->ParseDateTime( $this->record->postdate );
	}

	/*
	=====================
	GetLastUpdated
	=====================
	*/
	public function GetLastUpdated()
	{
		$this->GetTextField( "textid" );
		return $this->text->textid->GetInfo()->mtime;
	}

	/*
	=====================
	GetDraftTimestamp
	=====================
	*/
	public function GetDraftTimestamp()
	{
		$this->GetTextField( "draftid" );
		return $this->record->ParseDateTime( 
			$this->text->draftid->GetInfo()->mtime 
		);
	}

	/*
	=====================
	GetLastUpdatedTimestamp
	=====================
	*/
	public function GetLastUpdatedTimestamp()
	{
		$this->GetTextField( "textid" );
		return $this->text->textid->GetInfo()->mtimestamp;
	}

	/*
	=====================
	GetLinkId
	=====================
	*/
	public function GetLinkId()
	{
		return $this->record->linkid;
	}

	/*
	=====================
	SetLinkId
	=====================
	*/
	public function SetLinkId( $lid )
	{
		$this->record->linkid = $lid;
	}


	/*
	=====================
	Save
	=====================
	*/
	public function Save()
	{
		$this->record->Flush();
	}

	/*
	=====================
	GetAccessKey

	Returns a key that can be used to verify that you are allowed to interact 
	with this post.
	=====================
	*/
	public function GetAccessKey( $type, $ttl = 86400 )
	{
		global $gSecrets;
		$postkey = $gSecrets->Get( "postkey" );
		if (!$postkey) {
			$postkey = random_bytes(32);
			$gSecrets->Put( "postkey", $postkey );
		}
		$dat = $this->id . ":" . (time() + $ttl);
		$sig = hash_hmac( "sha256", $dat, $postkey, true );
		return base64_encode( $dat ) . ":" . base64_encode( $sig );
	}

	/*
	=====================
	CheckAccessKey

	Checks a given key. If valid, returns the post ID it specifies.
	=====================
	*/
	public static function CheckAccessKey( $key, $type )
	{
		global $gSecrets;
		if (!is_string( $key )) {
			return false;
		}
		$keysig = explode( ":", $key );
		if (count( $keysig ) != 2) {
			return false;
		}
		$got = base64_decode( $keysig[0] );
		$sig = base64_decode( $keysig[1] );

		$postkey = $gSecrets->Get( "postkey" );
		if (!$postkey) {
			return false;
		}
		$vfy = hash_hmac( "sha256", $got, $postkey, true );
		if (!hash_equals( $vfy, $sig )) {
			return false;
		}
		$got = explode( ":", $got );
		if (count( $got ) != 2) {
			return false;
		}
		if ((double)$got[1] < time()) {
			return false;
		}
		return $got[0];
	}



	/*
	=====================
	ListBlogPosts
	=====================
	*/
	public static function ListBlogPosts( $blogid, $type = null, $options = null )
	{
		$f = [ "blogid" => $blogid ];
		if ($type == "draft") {
			$f["published"] = 0;
			$f["deleted"] = 0;
		} else if ($type == "published" || $type === null) {
			$f["published"] = 1;
			$f["deleted"] = 0;
		} else if ($type == "deleted") {
			$f["published"] = 0;
			$f["deleted"] = 1;
		} else if ($type != "all") {
			throw new \Exception( "Unknown type for list posts: $type" );
		}
		$got = self::Find( $f, [ 
			"order" => "postdate", "reverse" => TRUE
		] );
		if (!$got) {
			return [];
		}
		//foreach ($got as &$val) {
		//	$val = new PostData( $val );
		//}
		return $got;
	}


	/*
	=====================
	Find

	Searches posts by field values. 
	Returns `false` or an array of PostData.
	=====================
	*/
	public static function Find( $fields, $options = NULL )
	{
		$rec = new SqlShadow( "posts" );
		$got = $rec->Find( $fields, $options );
		if (!$got) {
			return false;
		}
		foreach ($got as &$val) {
			$val = new PostData( $val );
		}
		return $got;
	}

	/*
	=====================
	Create

	Creates a new post.
	=====================
	*/
	public static function Create( $blogid, $authorid = null )
	{
		if (!$blogid) {
			throw new \Exception( "blog ID is required" );
		}
		$rec = new SqlShadow( "posts" );
		$rec->blogid = $blogid;
		$rec->authorid = $authorid;
		if (!$rec->Flush()) {
			throw new \Exception( "unable to create blog" );
		}
		return new PostData( $rec );
	}

	/*
	=====================
	Load

	Gets a PostData instance for the given id.
	=====================
	*/
	public static function Load( $postid )
	{
		$rec = new SqlShadow( "posts", [ "postid" => $postid ] );
		if (!$rec->Load()) {
			throw new \Exception( "unable to load post $postid" );
		}
		return new PostData( $rec );
	}
}
