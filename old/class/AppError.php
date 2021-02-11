<?php /* Copyright (C) 2017 David O'Riva. MIT License.
       ********************************************************/
namespace Typeractive;

/*
================================================================================

AppError

Construct with an error code and message. Error codes are things like
"AccessDenied" or "UnknownUser".

================================================================================
*/
class AppError extends \Exception
{
	var $errorName;

	/*
	=====================
	__construct
	=====================
	*/
	public function __construct( $code, $message = null, $prev = null )
	{	
		if ($message === null && strcspn( $code, " ,-:!" ) != strlen( $code )) {
			$message = $code;
			$code = null;
		}
		$this->errorName = $code;
		parent::__construct( $message, 0, $prev );
	}

	/*
	=====================
	getError
	=====================
	*/
	public function getError()
	{
		return $this->errorName !== null ? $this->errorName : "UnknownError";
	}

	/*
	=====================
	userMessage
	=====================
	*/
	public function userMessage()
	{
		if ($this->errorName) {
			if ($this->message !== null && $this->message !== "") {
				return $this->errorName . ": " . $this->message;
			}
			return "Error: " . $this->errorName;
		}
		return "Error: " . $this->message;
	}
}
