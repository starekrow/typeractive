<?php /* Copyright (C) 2017 David O'Riva. MIT License.
       ********************************************************/
namespace Typeractive;

/*
================================================================================

LoginServer

================================================================================
*/

class LoginServer extends AjaxServer
{
	/*
	=====================
	ShowError
	=====================
	*/
	public function ShowError( $str )
	{
		return [
			"run" => 'login_error(' .
					json_encode( $str ) .
				");"
		];
	}

	/*
	=====================
	HandleRequest
	=====================
	*/
	public function HandleRequest()
	{
		if (Http::$method !== "POST") {
			Http::MethodNotAllowed();
			return $this->ShowError( "Login form submission error" );
		}
		$args = $this->args;
		if ($args->password === null || $args->username === null) {
			return $this->ShowError( "Login form submission error" );
		}
		$user = UserData::LookupUsername( $args->username );
		if (!$user) {
			return $this->ShowError( "Unknown user" );
		}
		if (!$user->CheckPassword( $args->password )) {
			return $this->ShowError( "Incorrect password" );
		}
		$info = [
			 "username" => $user->GetName()
			,"id" => $user->id
		];
		return [
			"run" => 'login_close(' .
					json_encode( $info ) . 
				");"
		];
	}
}
