<?php /* Copyright (C) 2017 David O'Riva. MIT License.
       ********************************************************/
namespace Typeractive;

/*
================================================================================

DashboardServer

Serves a user's dashboard.

================================================================================
*/

class DashboardServer extends HtmlServer
{
	/*
	=====================
	NeedToSignIn
	=====================
	*/
	function NeedToSignIn()
	{
		$this->html = [
			 '<div style="margin-top:100px">'
			,'You must <a href="#signin" onclick="login_start();return false;">sign in</a> to view your dashboard.'
			,'</div>'
		];
	}

	/*
	=====================
	GetPage
	=====================
	*/
	function GetPage()
	{
		Http::StopClientCache();
		if (empty($_SESSION['userid'])) {
			return $this->NeedToSignIn();
		}
		$this->html = file_get_contents( "res/dashboard.html" );
		$this->location = Http::$appRootUrl . "/-/dashboard";
	}
}
