<?php /* Copyright (C) 2017 David O'Riva. MIT License.
       ********************************************************/
namespace Typeractive;

/*
================================================================================

TestJob

Test worker for jobs

================================================================================
*/

class TestJob extends JobWorker
{
	/*
	=====================
	__construct
	=====================
	*/
	function __construct( $job )
	{
		parent::__construct( $job );
	}
	/*
	=====================
	Go
	=====================
	*/
	function Go()
	{
		error_log( "Test job ran!" );
	}
}
