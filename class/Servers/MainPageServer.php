<?php /* Copyright (C) 2017 David O'Riva. MIT License.
       ********************************************************/
namespace Typeractive;

/*
================================================================================

MainPageServer

Serves the main (home) page of the site.

================================================================================
*/

class MainPageServer extends HtmlServer
{
	/*
	=====================
	GetPage
	=====================
	*/
	function GetPage()
	{
		$this->html = "<div>MetalCoder welcomes you!</div>";
	}
}
