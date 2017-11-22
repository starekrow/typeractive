<?php /* Copyright (C) 2017 David O'Riva. MIT License.
       ********************************************************/
namespace Typeractive;

/*
================================================================================

LinkData

Management for link names.

These actually aren't necessarily permanent, but they do provide a handy 
mechanism for creating unique links for arbitrary items. Multiple links to
the same item are also possible.

================================================================================
*/

class LinkData
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
		$this->id = $record->linkid;
	}

	/*
	=====================
	GetType
	=====================
	*/
	public function GetType()
	{
		return $this->record->type;
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
	SetOwner
	=====================
	*/
	public function SetOwner( $user )
	{
		$this->record->ownerid = $user;
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
	GetReference
	=====================
	*/
	public function GetReference()
	{
		return $this->record->otherid;
	}

	/*
	=====================
	SetReference
	=====================
	*/
	public function SetReference( $id )
	{
		$this->record->otherid = $id;
	}

	/*
	=====================
	ChangeLink
	=====================
	*/
	public function ChangeLink( $path )
	{
		if ($path === $this->record->link) {
			return true;
		}
		$olink = $this->record->link;
		$this->record->link = $path;
		if ($this->record->Flush() === false) {
			$this->record->link = $olink;
			return false;
		}
		return true;
	}


	/*
	=====================
	GetLink
	=====================
	*/
	public function GetLink()
	{
		return $this->record->link;
	}

	/*
	=====================
	GetParent
	=====================
	*/
	public function GetParent()
	{
		return $this->record->parentid;
	}

	/*
	=====================
	Save
	=====================
	*/
	public function Save()
	{
		return $this->record->Flush();
	}

	/*
	=====================
	Delete
	=====================
	*/
	public static function Delete( $id )
	{
		$rec = new SqlShadow( "links" );
		$rec->linkid = $id;
		if (!$rec->Load()) {
			return false;
		}
		$rec = new SqlShadow( "links" );
		$rec->linkid = $id;
		return $rec->Delete();
	}

	/*
	=====================
	LookupPrefix

	Tries to locate a matching link.
	=====================
	*/
	public static function LookupPrefix( $link, $type = null, $parent = null )
	{
		$rec = new SqlShadow( "links" );
		$rec->link = $link;
		if ($type) {
			$rec->type = $type;
		}
		if ($parent) {
			$rec->parentid = $parentid;
		}
		if (!$rec->Load()) {
			return false;
		}
		return new LinkData( $rec );
	}

	/*
	=====================
	Lookup

	Tries to locate a matching link.
	=====================
	*/
	public static function Lookup( $link, $type = null, $parent = null )
	{
		$rec = new SqlShadow( "links" );
		$rec->link = $link;
		if ($type) {
			$rec->type = $type;
		}
		if ($parent) {
			$rec->parentid = $parentid;
		}
		if (!$rec->Load()) {
			return false;
		}
		return new LinkData( $rec );
	}

	/*
	=====================
	Find

	Searches links by field values. 
	Returns `false` or an array of PageData.
	=====================
	*/
	public static function Find( $fields, $options = NULL )
	{
		$rec = new SqlShadow( "links" );
		$got = $rec->Find( $fields, $options );
		if (!$got) {
			return false;
		}
		foreach ($got as &$val) {
			$val = new LinkData( $val );
		}
		return $got;
	}



	/*
	=====================
	Load
	=====================
	*/
	public static function Load( $id )
	{
		$rec = new SqlShadow( "links" );
		$rec->linkid = $id;
		if (!$rec->Load()) {
			return false;
		}
		return new LinkData( $rec );
	}

	/*
	=====================
	Register

	Create a new link. Returns `false` if creation failed, or a link instance.

	Do not encode the link name; they're looked up after any URL-decoding step.
	=====================
	*/
	public static function Register( $link, $type, $id, $parent = null )
	{
		$rec = new SqlShadow( "links" );
		$rec->link = $link;
		$rec->type = $type;
		$rec->otherid = $id;
		$rec->parentid = $parent;
		if (!$rec->Insert()) {
			return false;
		}
		return new LinkData( $rec );
	}

}
