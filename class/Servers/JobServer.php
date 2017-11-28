<?php /* Copyright (C) 2017 David O'Riva. MIT License.
       ********************************************************/
namespace Typeractive;

/*
================================================================================

JobServer

Background and maintenance job control.

Jobs can be scheduled and distributed with this server. Job execution is 
triggered by hitting a specific URL with a cron job. This is left exposed, as 
it is (generally) harmless to trigger it more often than expected.

Important entry points:

* -/job/tick - trigger job execution
* -/job/list - see pending jobs, in order
* -/job/add - manually add a job to the queue
* -/job/log - recent job activity

"tick" is the only publicly accessible function; the others require site admin 
privileges.

================================================================================
*/

class JobServer extends HtmlServer
{

	/*
	=====================
	tick_reply
	=====================
	*/
	function tick_reply( $next )
	{
		if ($next === null || $next > 60) {
			$next = 60;
		}
		if ($next < 0) {
			$next = 0;
		}
		$this->ReplyText( (string)$next );
	}

	/*
	=====================
	cmd_tick
	=====================
	*/
	function cmd_tick( $path )
	{
		$job = JobData::LoadReady(1);
		if (!$job) {
			return $this->tick_reply( null );
		}
		$job = $job[0];
		if ($job->start > time()) {
			return $this->tick_reply( $job->start - time() );
		}
		if (!$job->Start()) {
			return $this->tick_reply( 0 );
		}

		try {
			$tp = $job->type;
			$tp = strtoupper( $tp[0] ) . strtolower( substr( $tp, 1 ) ) . "Job";
			$tp = "Typeractive\\$tp";
			if (!class_exists( $tp )) {
				// ack!
				error_log( "Unknown job type " . $job->type );
				return $this->tick_reply( 0 );
			}
			ob_start();
			$jc = new $tp( $job );
			$jc->Go();
			ob_end_clean();
		} catch (Exception $e) {
			error_log( "job " . $job->id . " failed: " . $e->getMessage() );
			if (!$job->GetRepeat()) {
				$job->SetState( "error" );
				return;
			}
		}
		if ($job->state == "running") {
			$job->Finish();
		}
		return $this->tick_reply( 0 );
	}		

	/*
	=====================
	cmd_list
	=====================
	*/
	function cmd_list( $path )
	{
		$jobs = JobData::Find( [] );
		//$job = JobData::LoadReady();
		if (!$jobs) {
			$jobs = [];
		}
		$out = [];
		foreach ($jobs as $j) {
			$out[] = [
				 "id" => $j->id
				,"type" => $j->type
				,"state" => $j->state
				,"command" => $j->command
				,"start" => $j->startdate
			];
		}
		$this->tokens->jobs = $out;
		$this->html = file_get_contents( "res/job_list.html" );
	}

	/*
	=====================
	GetPage
	=====================
	*/
	function GetPage()
	{
		$this->user = null;
		if (!empty($_SESSION['userid'])) {
			$this->user = UserData::Load( $_SESSION['userid'] );
		}
		if ($this->user) {
			$auth = $this->user->CheckPriv( "jobadmin" );
			if (!$auth) {
				$auth = $this->user->CheckPriv( "root" );
			}
		}
		$pp = explode( "/", $this->path );
		if (count($pp) && preg_match( "/^[_a-zA-Z0-9]+$/", $pp[1] )) {
			$cmd = "cmd_" . $pp[1];
			if ($cmd == "cmd_tick") {
				$auth = true;
			}
			if ($auth && method_exists( $this, $cmd )) {
				return $this->$cmd( array_slice( $pp, 2 ) );
			}
		}
		Http::NotFound();
		$this->html = "No such job function.";
	}
}
