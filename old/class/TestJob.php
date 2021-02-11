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
	Start
	=====================
	*/
	function Start()
	{
		error_log( "Test job ran!" );
	}
}
