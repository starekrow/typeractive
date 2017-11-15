<?php /* Copyright (C) 2017 David O'Riva. MIT License.
       ********************************************************/
namespace Typeractive;

/*
================================================================================

MainPageServer

Serves the main (home) page of the site.

================================================================================
*/

class MainPageServer extends PageServer
{
	/*
	=====================
	GetPage
	=====================
	*/
	function GetPage()
	{
		$this->html = "<center>MetalCoder welcomes you!</center>";
	}
}
