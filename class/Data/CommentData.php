<?php /* Copyright (C) 2017 David O'Riva. MIT License.
       ********************************************************/
namespace Typeractive;

/*
================================================================================

CommentData

================================================================================
*/

class CommentData
{
	public $id;
	protected $record;
	protected $textrecs;

	/*
	=====================
	__construct
	=====================
	*/
	protected function __construct( $record )
	{
		$this->record = $record;
		$this->id = $record->postid;
		$this->textrecs = (object)[];
	}

	/*
	=====================
	GetTextField
	=====================
	*/
	protected function GetTextField( $field )
	{
		if (!empty( $this->textrecs->$field )) {
			return $this->textrecs->$field->GetText();
		}
		if (!$this->record->$field) {
			return "";
		}
		// TODO: safety checks
		$t = TextData::Load( $this->record->$field );
		$this->textrecs->$field = $t;
		return $t->GetText();
	}

	/*
	=====================
	SetTextField
	=====================
	*/
	protected function SetTextField( $field, $text, $type = null )
	{
		// TODO: safety checks?
		if ($text == null) {
			$text = "";
		}
		if ($type === null) {
			$type = "v1:$field:markdown";
		}
		if (!$this->record->$field) {
			$t = TextData::Create( 
				$text, 
				$type, 
				$this->record->authorid 
			);
			$this->textrecs->$field = $t;
			$this->record->$field = $t->id;
		} else {
			// TODO: history
			if (empty( $this->textrecs->$field )) {
				$this->GetTextField( $field );
			}
			$this->textrecs->$field->SetText( $text );
			$this->textrecs->$field->Save();
		}
	}

	/*
	=====================
	GetInfo
	=====================
	*/
	public function GetInfo()
	{
		$authname = $this->record->author;
		if ($this->record->authorid && $this->record->author_username) {
			$authname = $this->record->author_username;
		}
		return (object)[
			 "authorid" => $this->record->authorid
			,"author" => $this->record->author
			,"authorname" => $this->record->authname
			,"postid" => $this->record->postid
			,"parentid" => $this->record->parentid
			,"score" => $this->record->score
			,"ctime" => $this->record->ctime
			,"ctimestamp" => $this->record->ParseDateTime( $this->record->ctime )
			,"mtime" => $this->record->text_mtime
			,"mtimestamp" => $this->record->ParseDateTime( $this->record->text_mtime )
			,"ip" => inet_ntop( $this->record->ip )
		];
	}

	/*
	=====================
	SetText
	=====================
	*/
	public function SetText( $text )
	{
		// TODO: safety checks?
		$this->SetTextField( "textid", $text, "v1:comment" );
	}

	/*
	=====================
	UpdateText
	=====================
	*/
	public function UpdateText( $text )
	{
		$nid = TextData::TrackChange( $this->record->textid, $text );
	}

	/*
	=====================
	SetState
	=====================
	*/
	public function SetState( $state )
	{
		$this->record->state = $state;
	}

	/*
	=====================
	GetState
	=====================
	*/
	public function GetState()
	{
		return $this->record->state;
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
		if ($this->record->text) {
			return $this->record->text;
		}
		return $this->GetTextField( "textid" );
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
	CountForPost
	=====================
	*/
	public static function CountForPost( 
		$postid, 
		$state = null )
	{
		$rec = new SqlShadow( "comments" );
		$got = $rec->Find( [
			 "postid" => $postid
			,"state" => $state ? $state : "public"
			,"*fetch" => "count of *"
		] );
		return ($got && count($got)) ? (int)$got[0] : 0;
	}


	/*
	=====================
	LoadForPost
	=====================
	*/
	public static function LoadForPost( 
		$postid, 
		$state = null, 
		$cursor = null )
	{
		$off = 0;
		$lim = 50;
		if ($cursor) {
			$cp = explode( ":", $cursor );
			$off = max( (int) $cp[0], 0 );
			$lim = max( (int) $cp[1], 1 );
		}
		return self::Find( [
			 "postid" => $postid
			,"state" => $state ? $state : "public"
			,"*limit" => $lim
			,"*join" => [ [
				 "table" => "users"
				,"on" => [ "userid" => ["comments.authorid"] ]
				,"fetch" => "name as author_username"
			  ],[
				 "table" => "text"
				,"on" => [ "textid" => ["comments.textid"] ]
				,"fetch" => [ "text", "mtime as text_mtime" ]
			  ]
			]
			,"*offset" => $off
		] );
	}


	/*
	=====================
	Find

	Searches posts by field values. 
	Returns `false` or an array of CommentData.
	=====================
	*/
	protected static function Find( $query )
	{
		$rec = new SqlShadow( "comments" );
		$got = $rec->Find( $query );
		if (!$got) {
			return [];
		}
		foreach ($got as &$val) {
			$val = new CommentData( $val );
		}
		return $got;
	}



	/*
	=====================
	Create

	Creates a new comment.
	=====================
	*/
	public static function Create( $data )
	{
		$d = new Dict( $data );
		if (!$blogid) {
			throw new \Exception( "blog ID is required" );
		}
		$rec = new SqlShadow( "comments" );
		$rec->postid = $d->postid;
		$rec->authorid = $d->authorid;
		$rec->score = $d->score ? $d->score : 0;
		$rec->ip = $d->ip ? inet_pton( $d->ip ) : null;
		$rec->parentid = $d->parentid;
		$rec->state = "pending";
		$rec->ctime = $rec->DateTime( time() );
		$t = TextData::Create( 
			$d->text, 
			"v1:comment", 
			$d->authorid 
		);
		$rec->text = $t->id;
		if (!$rec->Flush()) {
			throw new \Exception( "unable to create comment" );
		}
		return new CommentData( $rec );
	}

	/*
	=====================
	Load

	Gets a CommentData instance for the given id.
	=====================
	*/
	public static function Load( $cid )
	{
		$rec = new SqlShadow( "comments", [ "commentid" => $cid ] );
		if (!$rec->Load()) {
			throw new \Exception( "unable to load comment $cid" );
		}
		return new CommentData( $rec );
	}
}
