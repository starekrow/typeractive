<?php /* Copyright (C) 2017 David O'Riva. MIT License.
       ********************************************************/
namespace Typeractive;

/*
================================================================================

TextData

Handling for text records

================================================================================
*/

class TextData
{
	public $id;
	protected $record;

	/*
	=====================
	__construct
	=====================
	*/
	protected function __construct( $record )
	{
		$this->record = $record;
		$this->id = $record->textid;
	}

	/*
	=====================
	GetInfo
	=====================
	*/
	public function GetInfo()
	{
		$r = $this->record;
		return (object)[
			 "author" => $r->authorid
			,"editor" => $r->editorid
			,"ctime" => $r->ctime
			,"mtime" => $r->mtime
			,"ctimestamp" => $r->ParseDateTime( $r->ctime )
			,"mtimestamp" => $r->ParseDateTime( $r->mtime )
			,"type" => $r->type
			,"text" => $r->text
		];
	}

	/*
	=====================
	GetText
	=====================
	*/
	public function GetText()
	{
		return $this->record->text;
	}


	/*
	=====================
	SetEditor

	Replaces the text in the record. No history is kept of the change.
	=====================
	*/
	public function SetEditor( $editorid )
	{
		$this->record->editorid = $editorid;
	}


	/*
	=====================
	SetText

	Replaces the text in the record. No history is kept of the change.
	=====================
	*/
	public function SetText( $newtext )
	{
		$this->record->text = $newtext;
		$this->record->mtime = $this->record->DateTime( microtime( true ) );
	}


	/*
	=====================
	UpdateText

	Updates the text in the record. A complete copy of the current text is 
	kept as a historical record.
	=====================
	*/
	public function UpdateText( $newtext, $updater )
	{
		// TODO: diffing
		$r = $this->record;
		$prev = self::Create( $r->text, $r->authorid );
		$p = $prev->record;
		$p->historyid = $r->textid;
		$p->ctime = $r->mtime;
		$r->mtime = $r->DateTime( microtime( true ) );
		$p->mtime = $r->mtime;
		$p->editor = $r->editor;
		$r->editor = $updater;
		$r->historyid = -1;		// mark existence of history
		$p->type = "full_text";
		$p->Flush();
		$r->Flush();
		return $p->textid;
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
	SetType
	=====================
	*/
	public function SetType( $type )
	{
		$this->record->type = $type;
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
	Create

	Creates new text.
	Returns a TextRecord, or false if the creation failed
	=====================
	*/
	public static function Create( $text = null, $type = null, $authorid = null )
	{
		$rec = new SqlShadow( "text" );
		$rec->ctime = $rec->DateTime( microtime( true ) );
		$rec->mtime = $rec->ctime;
		$rec->text = $text;
		$rec->type = $type;
		$rec->authorid = $authorid;
		if (!$rec->Flush()) {
			throw new \Exception( "unable to create text" );
		}
		return new TextData( $rec );
	}

	/*
	=====================
	Load

	Gets a TextRecord instance for the given id.
	=====================
	*/
	public static function Load( $textid )
	{
		$rec = new SqlShadow( "text", [ "textid" => $textid ] );
		if (!$rec->Load()) {
			throw new \Exception( "unable to load blog $textid" );
		}
		return new TextData( $rec );
	}
}
