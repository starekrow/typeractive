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
	// How long to allow any one job to run
	const MAX_JOB_TIME = 3600;
	// How long to allow a single tick request to run
	const MAX_TICKER = 300;
	// Expire the tick request lock a little bit early
	const TICK_UNLOCK_OFFSET = 15;
	// Seconds between automatic job pickup
	const TICK_INTERVAL = 30;

	/*
	=====================
	Schedule
	=====================
	*/
	static function Schedule( $name, $type, $command, $delay = 0, 
		$data = null, $runlimit = null, $repeat = null )
	{
		$j = JobData::Create( $type );
		$j->SetName( $name );
		$j->SetCommand( $command );
		$j->SetData( $data );
		$j->SetRunLimit( $runlimit );
		$j->SetRepeat( $repeat );
		$j->Schedule( time() + $delay );
	}


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
		// allow up to an hour before the process gets killed (hopefully)
		set_time_limit( self::MAX_JOB_TIME );
		$runtime = 0;
		$until = time();
		if ($this->args->runtime) {
			$runtime = min( (int) $this->args->runtime, self::MAX_TICKER );
			$until += $runtime;
			if (!Cache::setx( "jobs_tick_watcher", 1, 
								$until - self::TICK_UNLOCK_OFFSET )
			   ) {
				$until -= $runtime;
			}
		}

		$wait = false;
		do {
			if ($wait) {
				sleep( $wait );
			}
			$wait = max(min($until - time() - 1, self::TICK_INTERVAL), 1);
			$job = JobData::LoadReady(1);
			if (!$job) {
				continue;
			}
			$job = $job[0];
			if ($job->start > time()) {
				$wait = min( $wait, $job->start - time() + 1 );
				continue;
			}
			if (!$job->Start()) {
				continue;
			}
			try {
				$tp = $job->type;
				$tp = strtoupper( $tp[0] ) . strtolower( substr( $tp, 1 ) );
				$tp = "Typeractive\\{$tp}Job";
				if (!class_exists( $tp )) {
					throw new \Exception( "Unknown job type " . $job->type );
				}
				ob_start();
				$jc = new $tp( $job );
				$jc->Start();
				ob_end_clean();
				$job->Finish();
			} catch (\Exception $e) {
				error_log( "job " . $job->id . " failed: " . $e->getMessage() );
				if ($job->GetRepeat()) {
					$job->SetState( "ready" );
				} else {
					$job->SetState( "error" );
				}
				$job->Save();
			}
			$wait = false;
		} while (time() < $until);
		return $this->tick_reply( $wait );
	}		

	/*
	=====================
	cmd_list
	=====================
	*/
	function cmd_list( $path )
	{
		$jobs = JobData::Find( "*" );
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
