<?php /* Copyright (C) 2017 David O'Riva. MIT License.
       ********************************************************/
namespace Typeractive;

/*
================================================================================

EventData

Management for events.

================================================================================
*/

class EventData
{
	public $id;
	protected $record;
	public $reference;
	public $type;
	public $timestamp;
	public $user;

	/*
	=====================
	__construct
	=====================
	*/
	protected function __construct( $record )
	{
		$this->record = $record;
		$this->id = $record->eventid;
		$this->timestamp = $this->record->ParseDateTime( $this->record->timestamp );
		$this->reference = $this->record->otherid;
		$this->type = $this->record->type;
		$this->user = $this->record->userid;
	}

	/*
	=====================
	SetData
	=====================
	* /
	public function SetData( $data )
	{
		$this->record->data = serialize( $data );
	}

	/*
	=====================
	GetData
	=====================
	*/
	public function GetData()
	{
		return unserialize( $this->record->data );
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
		$rec = new SqlShadow( "events" );
		$rec->eventid = $id;
		if (!$rec->Load()) {
			return false;
		}
		$rec = new SqlShadow( "events" );
		$rec->eventid = $id;
		return $rec->Delete();
	}

	/*
	=====================
	Find

	Searches events by field values. 
	Returns `false` or an array of EventData.
	=====================
	*/
	public static function Find( $fields, $options = NULL )
	{
		$rec = new SqlShadow( "events" );
		$got = $rec->Find( $fields, $options );
		if (!$got) {
			return false;
		}
		foreach ($got as &$val) {
			$val = new EventData( $val );
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
		$rec = new SqlShadow( "events" );
		$rec->eventid = $id;
		if (!$rec->Load()) {
			return false;
		}
		return new EventData( $rec );
	}

	/*
	=====================
	Create

	Create a new event. Returns `false` if creation failed, or an event 
	instance.
	=====================
	*/
	public static function Create( $type, $user, 
		$reference = null, $data = null )
	{
		$rec = new SqlShadow( "events" );
		$rec->type = $type;
		$rec->user = $user;
		$rec->timestamp = $rec->DateTime( microtime( true ) );
		$rec->otherid = $reference;
		$rec->data = serialize( $data );
		if (!$rec->Insert()) {
			return false;
		}
		return new EventData( $rec );
	}

}
