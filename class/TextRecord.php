<?php /* Copyright (C) 2017 David O'Riva. MIT License.
       ********************************************************/
namespace Typeractive;

/*
================================================================================

Text

Handling for text records

================================================================================
*/

class TextRecord
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
		];
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
		$r->mtime = date("Y-m-d H:i:s", microtime( true ));
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
	Create

	Creates new text.
	Returns a TextRecord, or false if the creation failed
	=====================
	*/
	public static function Create( $text = null, $authorid = null )
	{
		$rec = new SqlShadow( "text" );
		$rec->ctime = microtime( true );
		$rec->text = $text;
		$rec->authorid = $authorid;
		if (!$rec->Flush()) {
			throw new \Exception( "unable to create text" );
		}
		return new TextRecord( $rec );
	}

	/*
	=====================
	Open

	Gets a TextRecord instance for the given id.
	=====================
	*/
	public static function Open( $textid )
	{
		$rec = new SqlShadow( "text", [ "textid" => $textid ] );
		if (!$rec->Load()) {
			throw new \Exception( "unable to load blog $textid" );
		}
		return new TextRecord( $rec );
	}
}
