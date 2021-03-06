<?php /* Copyright (C) 2017 David O'Riva. MIT License.
       ********************************************************/
namespace Typeractive;

/*
================================================================================

Server

Generic support for responding to user requests. Most request handlers 
will likely subclass this. 

The default server type responds by echoing text to the console. You can use 
output buffering to capture this if desired. These servers will generally try
to produce a complete HTTP response with appropriate headers, etc.

================================================================================
*/

class Server
{
	protected $request;
	protected $path;
	protected $args;
	protected $headers;
	protected $method;

	protected $didReply;
	protected $replyType;
	protected $contentType;
	protected $result;


	/*
	=====================
	Reply
	=====================
	*/
	public function Reply( $value = null )
	{
		if ($value === null) {
			$value = $this->result;
		}
		if (!$this->replyType) {
			$this->replyType = $this->DefaultReplyType();
		}
		switch ($this->replyType) {
		case "internal":
			return $value;
		case "json":
			$type = "application/json";
			$value = json_encode( $value );
			break;
		case "text":
		case "custom":
			$type = "text/plain";
			$value = (string) $value;
			break;
		case "raw":
			$type = "application/octet-stream";
			$value = (string) $value;
			break;
		case "html":
		default:
			$type = "text/html";
			if (is_array( $value ) && isset( $value[0] )) {
				$value = implode( "", $value );
			} else if (is_array( $value ) || is_object( $value )) {
				$value = new Dict( $value );
				$value = implode( "", [
					 "<!DOCTYPE html><html><head>"
					,$value->metatags
					,"<title>"
					,htmlspecialchars( $value->title )
					,"</title><style type=\"text/css\">"
					,$value->css
					,"</style><script type=\"text/javascript\">"
					,$value->script
					,$value->js
					,"</script></head><body>"
					,$value->body
					,$value->html
					,"</body></html>"
				] );
			} else {
				$value = (string) $value;
			}
			break;
		}
		if ($this->contentType) {
			$type = $this->contentType;
		}
		header( "Content-Type: $type" );
		echo $value;
		$this->didReply = true;
		return $value;
	}

	/*
	=====================
	ReplyJson
	=====================
	*/
	public function ReplyJson( $value )
	{
		$this->replyType = "json";
		return $this->Reply( $value );
	}

	/*
	=====================
	ReplyRaw
	=====================
	*/
	public function ReplyRaw( $value )
	{
		$this->replyType = "raw";
		return $this->Reply( $value );
	}

	/*
	=====================
	ReplyText
	=====================
	*/
	public function ReplyText( $value )
	{
		$this->replyType = "text";
		return $this->Reply( $value );
	}

	/*
	=====================
	ReplyHtml
	=====================
	*/
	public function ReplyHtml( $value, $type = null )
	{
		$this->replyType = "html";
		return $this->Reply( $value );
	}

	/*
	=====================
	DefaultReplyType
	=====================
	*/
	function DefaultReplyType()
	{
		return "text";
	}

	/*
	=====================
	RequestHandler
	=====================
	*/
	function RequestHandler()
	{
		return "Nothing to serve";
	}

	/*
	=====================
	SetupRequest
	=====================
	*/
	public function SetupRequest( $request )
	{
		$this->request = $r = new Dict( $request );
		$this->path = isset( $r->path ) ? $r->path : "";
		$this->args = new Dict( $r->args );
		$this->headers = new Dict( $r->headers );
		$this->method = $r->method;
	}

	/*
	=====================
	GetResponse
	=====================
	*/
	public function GetResponse()
	{
		$got = $this->RequestHandler();
		if (!$this->didReply) {
			$this->Reply( $got );
		}
		return $got;
	}

	/*
	=====================
	Handle
	=====================
	*/
	public static function Handle( $req )
	{
		$cn = get_called_class();
		$inst = new $cn();
		$inst->SetupRequest( $req );
		return $inst->GetResponse();
	}

}
