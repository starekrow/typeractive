<?php /* Copyright (C) 2017 David O'Riva. MIT License.
       ********************************************************/
namespace Typeractive;

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
	Save
	=====================
	*/
	public function Save()
	{
		$this->record->Flush();
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
