<?php /* Copyright (C) 2017 David O'Riva. MIT License.
       ********************************************************/
namespace Typeractive;

/*
================================================================================

JobWorker

Superclass for jobs.

================================================================================
*/

abstract class JobWorker
{
	protected $job;
	protected $command;
	protected $data;
	public $id;

	/*
	=====================
	__construct
	=====================
	*/
	function __construct( $job )
	{
		$this->id = $job->id;
		$this->command = $job->command;
		$this->data = $job->GetData();
		$this->job = $job;
	}

	/*
	=====================
	Start
	=====================
	*/
	abstract function Start();
}
