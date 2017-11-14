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
	GetReference
	=====================
	*/
	public function GetReference()
	{
		return $this->record->otherid;
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
	Lookup

	Tries to locate a matching permalink.
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
		return new Permalink( $rec );
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
		$rec->parent = $parent;
		if (!$rec->Insert()) {
			return false;
		}
		return new Permalink( $rec );
	}

}
