<?php /* Copyright (C) 2017 David O'Riva. MIT License.
       ********************************************************/
namespace Typeractive;

/*
================================================================================

JobData

Management for jobs.

================================================================================
*/

class JobData
{
	public $id;
	protected $record;
	public $start;
	public $startdate;
	public $type;
	public $state;
	public $name;
	public $command;
	public $expires;

	/*
	=====================
	__construct
	=====================
	*/
	protected function __construct( $record )
	{
		$this->record = $record;
		$this->id = $record->jobid;
		$this->startdate = $this->record->start;
		$this->start = $this->record->ParseDateTime( $this->record->start );
		$this->expires = $this->record->ParseDateTime( $this->record->expires );
		$this->type = $this->record->type;
		$this->command = $this->record->command;
		$this->state = $this->record->state;
		$this->name = $this->record->name;
	}

	/*
	=====================
	SetType
	=====================
	*/
	public function SetType( $type )
	{
		$this->type = $this->record->type = $type;
	}

	/*
	=====================
	SetState
	=====================
	*/
	public function SetState( $state )
	{
		$this->state = $this->record->state = $state;
	}

	/*
	=====================
	SetName
	=====================
	*/
	public function SetName( $name )
	{
		$this->name = $this->record->name = $name;
	}

	/*
	=====================
	SetData
	=====================
	*/
	public function SetData( $data )
	{
		if ($data === null) {
			$this->record->data = null;
		} else {
			$this->record->data = serialize( $data );
		}
	}

	/*
	=====================
	GetData
	=====================
	*/
	public function GetData()
	{
		if ($this->record->data === null) {
			return null;
		}
		return unserialize( $this->record->data );
	}

	/*
	=====================
	SetRunLimit
	=====================
	*/
	public function SetRunLimit( $limit )
	{
		$this->record->runlimit = $limit;
	}

	/*
	=====================
	SetStart
	=====================
	*/
	public function SetStart( $val )
	{
		$this->start = $val;
		$this->startdate = $this->record->start = 
			$this->record->DateTime( $val );
	}


	/*
	=====================
	SetRepeat
	=====================
	*/
	public function SetRepeat( $repeat )
	{
		$this->record->repeat = $repeat;
	}

	/*
	=====================
	GetRepeat
	=====================
	*/
	public function GetRepeat( $repeat )
	{
		$this->record->repeat = $repeat;
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
		$rec = new SqlShadow( "jobs" );
		$rec->jobid = $id;
		if (!$rec->Load()) {
			return false;
		}
		$rec = new SqlShadow( "jobs" );
		$rec->jobid = $id;
		return $rec->Delete();
	}

	/*
	=====================
	Start

	Tries to mark this job started. Returns `true` if successful.
	=====================
	*/
	public function Start()
	{
		$rec = $this->record;
		$rec->state = "running";
		$limit = $rec->runlimit ? $rec->runlimit : 300;
		$rec->expires = $rec->DateTime( time() + $limit );
		$did = $rec->Update([ 
			"jobid" => $rec->jobid, 
			"state" => "ready"]);
		if (!$did) {
			return $did;
		}
		$this->state = "running";
		return $did;
	}

	/*
	=====================
	Finish

	Tries to mark this job completed. Returns `true` if successful.
	Repeating jobs will be rescheduled.
	=====================
	*/
	public function Finish()
	{
		$rec = $this->record;
		$rec->state = "done";
		if ($rec->repeat) {
			$next = $this->start + $rec->repeat;
			while ($next < time()) {
				$next += $rec->repeat;
			}
			$this->SetStart( $next );
			$rec->state = "ready";
		} else {
			$rec->expires = time() + 24 * 60 * 60;
		}
		$did = $rec->Update([ 
			"jobid" => $rec->jobid, 
			"state" => "running" ]);
		if ($did) {
			$this->state = $rec->state;
			$this->expires = $rec->expires;
		}
		return $did ? true : false;
	}

	/*
	=====================
	Schedule
	=====================
	*/
	public function Schedule( $time )
	{
		$this->SetStart( $time );
		$this->SetState( "ready" );
		$this->Save();
	}

	/*
	=====================
	Find

	Searches jobs by field values. 
	Returns `false` or an array of PageData.
	=====================
	*/
	public static function Find( $fields, $options = NULL )
	{
		$rec = new SqlShadow( "jobs" );
		if ($fields == "*") {
			$fields = [ "*filter" => [] ];
		}
		$got = $rec->Find( $fields, $options );
		if (!$got) {
			return false;
		}
		foreach ($got as &$val) {
			$val = new JobData( $val );
		}
		return $got;
	}

	/*
	=====================
	LoadReady
	=====================
	*/
	public static function LoadReady( $max = null )
	{
		$rec = new SqlShadow( "jobs" );
		$got = $rec->Find( [ 
			"state" => "ready",
		 	"*limit" => $max,
		 	"*order" => "start" ] );
		if (!$got) {
			return false;
		}
		foreach ($got as &$val) {
			$val = new JobData( $val );
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
		$rec = new SqlShadow( "jobs" );
		$rec->jobid = $id;
		if (!$rec->Load()) {
			return false;
		}
		return new JobData( $rec );
	}

	/*
	=====================
	Create

	Create a new job. Returns `false` if creation failed, or a job instance.
	The job's state is set to "new". You must call Schedule() when the
	job is actually ready to execute.

	The job will expire within 5 minutes if you don't do anything more with
	it.
	=====================
	*/
	public static function Create( $type, $command = null )
	{
		$rec = new SqlShadow( "jobs" );
		$rec->type = $type;
		$rec->state = "new";
		$rec->start = $rec->DateTime( null );
		$rec->expires = $rec->DateTime( time() + 300 );
		$rec->command = $command;
		if (!$rec->Insert()) {
			return false;
		}
		return new JobData( $rec );
	}

}
