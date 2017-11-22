<?php /* Copyright (C) 2017 David O'Riva. MIT License.
       ********************************************************/
namespace Typeractive;

/*
================================================================================

PageData

Represents a single page. Use factory methods to instantiate. 

================================================================================
*/

class PageData
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
		$this->id = $record->pageid;
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
				$this->record->ownerid 
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
	SetBody
	=====================
	*/
	public function SetBody( $text )
	{
		$this->SetTextField( "bodyid", $text );
	}

	/*
	=====================
	SetName
	=====================
	*/
	public function SetName( $text )
	{
		$this->record->name = $text;
	}

	/*
	=====================
	GetOwner
	=====================
	*/
	public function GetOwner()
	{
		return $this->record->ownerid;
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
	GetBody
	=====================
	*/
	public function GetBody()
	{
		return $this->GetTextField( "bodyid" );
	}

	/*
	=====================
	GetPageId
	=====================
	*/
	public function GetPageId()
	{
		return $this->record->pageid;
	}

	/*
	=====================
	GetCreatedTimestamp
	=====================
	*/
	public function GetCreatedTimestamp()
	{
		$this->GetTextField( "bodyid" );
		return $this->text->bodyid->GetInfo()->mtimestamp;
	}

	/*
	=====================
	GetUpdatedTimestamp
	=====================
	*/
	public function GetUpdatedTimestamp()
	{
		$this->GetTextField( "bodyid" );
		return $this->text->bodyid->GetInfo()->mtimestamp;
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
	ListPages
	=====================
	*/
	public static function ListPages( $userid, $options = null )
	{
		$f = [ "ownerid" => $userid ];
		$got = self::Find( $f, [ 
			"order" => "name"
		] );
		if (!$got) {
			return [];
		}
		return $got;
	}


	/*
	=====================
	Find

	Searches pages by field values. 
	Returns `false` or an array of PageData.
	=====================
	*/
	public static function Find( $fields, $options = NULL )
	{
		$rec = new SqlShadow( "pages" );
		$got = $rec->Find( $fields, $options );
		if (!$got) {
			return false;
		}
		foreach ($got as &$val) {
			$val = new PageData( $val );
		}
		return $got;
	}

	/*
	=====================
	Create

	Creates a new page.
	=====================
	*/
	public static function Create( $ownerid, $name = null )
	{
		if (!$ownerid) {
			throw new AppError( "BadParameter", "owner ID is required" );
		}
		$rec = new SqlShadow( "pages" );
		$rec->ownerid = $ownerid;
		$rec->name = $name;
		if (!$rec->Flush()) {
			throw new AppError( "DatabaseFault", "unable to create page" );
		}
		return new PageData( $rec );
	}

	/*
	=====================
	Load

	Gets a PageData instance for the given id.
	=====================
	*/
	public static function Load( $pageid )
	{
		$rec = new SqlShadow( "pages", [ "pageid" => $pageid ] );
		if (!$rec->Load()) {
			throw new AppError( "NotFound", "unable to load page $pageid" );
		}
		return new PageData( $rec );
	}
}
