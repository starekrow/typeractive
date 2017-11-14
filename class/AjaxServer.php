<?php /* Copyright (C) 2017 David O'Riva. MIT License.
       ********************************************************/
namespace Typeractive;

/*
================================================================================

AjaxServer

General support for responding to user requests. Most ajax request handlers 
will likely subclass this.

The ajax server generally expects a serializable value to be produced in 
response to a request. The default configuration emits JSON-encoded values.

================================================================================
*/

class AjaxServer extends Server
{
	/*
	=====================
	DefaultReplyType
	=====================
	*/
	public function DefaultReplyType()
	{
		return "json";
	}
}
