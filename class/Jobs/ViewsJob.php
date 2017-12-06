<?php /* Copyright (C) 2017 David O'Riva. MIT License.
       ********************************************************/
namespace Typeractive;

/*
================================================================================

ViewsJob

================================================================================
*/

class ViewsJob extends JobWorker
{
	/*
	=====================
	Start
	=====================
	*/
	function Start()
	{
		$vfn = Logs::Roll( "views" );
		if (!$vfn) {
			Logs::Stamp( "jobs", "Ran views - no new views" );
			return;
		}
		$f = fopen( $vfn, "rb" );
		$cnt = 0;
		for (;($l = fgets( $f ));) {
			$l = json_decode( $l );
			$type = $l[0];
			$id1 = $l[1];

			$v = ViewData::Create( $l[0], $l[1], $l[3] );
			$v->SetSubject2( $l[2] );
			$v->SetUrl( $l[4] );
			$v->SetQuery( $l[5] );
			$v->SetReferrer( $l[6] );
			$v->SetIP( $l[7] );

			$v->Save();
			++$cnt;
		}
		fclose( $f );
		unlink( $vfn );
		Logs::Stamp( "jobs", "Processed $cnt views" );
	}
}
