<?php /* Copyright (C) 2017 David O'Riva. MIT License.
       ********************************************************/
namespace Typeractive;

/*
================================================================================

ViewData

================================================================================
*/

class ViewData
{
	public $id;
	protected $record;
	protected static $textcache = [];
	protected static $textidcache = [];

	/*
	=====================
	__construct
	=====================
	*/
	protected function __construct( $record )
	{
		$this->record = $record;
		$this->id = $record->viewid;
		$this->type = $record->type;
		$this->subject = $record->subject;
	}

	/*
	=====================
	GetInfo
	=====================
	*/
	public function GetInfo()
	{
		$r = $this->record;
		return (object)[
			// "url" => $this->LoadText( $r->url )
			//,"referrer" => $this->LoadText( $r->referrer )
			//,"query" => $this->LoadText( $r->query )
			 "time" => $r->time
			,"timestamp" => $r->ParseDateTime( $r->time )
			,"type" => $r->type
			,"subject" => $r->subject
			,"subject2" => $r->subject2
			,"ip" => inet_ntop( $r->ip )
			,"id" => $r->viewid
		];
	}

	/*
	=====================
	RegisterText
	=====================
	*/
	public static function RegisterText( $id, $text )
	{
		self::$textcache[ $id ] = $text;
		self::$textidcache[ $text ] = $id;
	}


	/*
	=====================
	LoadText
	=====================
	*/
	public static function LoadText( $id )
	{
		if (is_array($id)) {
			$todo = array_unique( $id );
			$t2 = [];
			foreach ($id as $t) {
				if (!array_key_exists( self::$textcache[ $t ] )) {
					$t2[] = $t;
				}
			}
			foreach ($t2 as $t) {
				self::LoadText( $t );
			}
			$out = [];
			foreach ($id as $t) {
				if (!array_key_exists( self::$textcache[ $t ] )) {
					$out[] = null;
				} else {
					$out[] = self::$textcache[ $t ];
				}
			}
			return $out;
		}
		if (!array_key_exists( $id, self::$textcache )) {
			$got = new SqlShadow( "viewtext" );
			if ($got->Load( $id )) {
				self::RegisterText( $id, $got->text );
			} else {
				error_log("internal error: missing view text link for id $id");
				self::$textcache[ $id ] = null;			
			}
		}
		return self::$textcache[ $id ];
	}

	/*
	=====================
	SaveText
	=====================
	*/
	public static function SaveText( $text )
	{
		if (!isset( $text )) {
			return null;
		}
		if (is_array($text)) {
			$todo = array_unique( $text );
			$t2 = [];
			foreach ($todo as $t) {
				if (!array_key_exists( self::$textidcache[ $t ] )) {
					$t2[] = $t;
				}
			}
			foreach ($t2 as $t) {
				$this->SaveText( $t );
			}
			$out = [];
			foreach ($text as $t) {
				if (!array_key_exists( self::$textidcache[ $t ] )) {
					$out[] = null;
				} else {
					$out[] = self::$textidcache[ $t ];
				}
			}
			return $out;
		}
		if (array_key_exists( $text, self::$textidcache )) {
			return self::$textidcache[ $id ];
		}
		for ($tries = 2; $tries; --$tries) {
			$vt = new SqlShadow( "viewtext" );
			if ($vt->Load( ["text" => $text] )) {
				self::RegisterText( $vt->vtid, $text );
				return $vt->vtid;
			}
			$vt->text = $text;
			if ($vt->Insert()) {
				self::RegisterText( $vt->vtid, $text );
				return $vt->vtid;
			}
		}
		error_log( "internal error: failed to create view text" );
		return null;
	}

	/*
	=====================
	SetUrl
	=====================
	*/
	public function SetUrl( $url )
	{
		$this->record->url = isset( $url ) ? self::SaveText( $url ) : $url;
	}

	/*
	=====================
	GetUrl
	=====================
	*/
	public function GetUrl()
	{
		return self::LoadText( $this->record->url );
	}

	/*
	=====================
	SetQuery
	=====================
	*/
	public function SetQuery( $q )
	{
		$this->record->query = isset( $q ) ? self::SaveText( $q ) : $q;
	}

	/*
	=====================
	GetQuery
	=====================
	*/
	public function GetQuery()
	{
		return self::LoadText( $this->record->query );
	}

	/*
	=====================
	SetReferrer
	=====================
	*/
	public function SetReferrer( $r )
	{
		$this->record->referrer = isset( $r ) ? self::SaveText( $r ) : $r;
	}

	/*
	=====================
	GetReferrer
	=====================
	*/
	public function GetReferrer()
	{
		return self::LoadText( $this->record->referrer );
	}

	/*
	=====================
	SetSubject2
	=====================
	*/
	public function SetSubject2( $s )
	{
		$this->record->subject2 = $s;
	}

	/*
	=====================
	SetIP
	=====================
	*/
	public function SetIP( $ip )
	{
		$this->record->ip = inet_pton( $ip );
	}

	/*
	=====================
	Save
	=====================
	*/
	public function Save()
	{
		$this->record->Flush();
	}

	/*
	=====================
	LookupDates

	Finds views by date range
	=====================
	*/
	public static function LookupDates( $sub1, $sub2, $start, $end )
	{
		$db = Sql::AutoConnect();
		$rec = new SqlShadow( "views" );
		$query = [
			"time" => [ "range", $rec->DateTime( $start ), 
								 $rec->DateTime( $end ) ]
			,"*join" => [
				 "table" => "viewtext"
				,"on" => [ "vtid" => ["views.referrer"] ]
				,"fetch" => "text as referrer_text"
			]
			,"*order" => "time"
		];
		if ($sub1) {
			$query["subject"] = $sub1;
		}
		if ($sub2) {
			$query["subject2"] = $sub2;
		}
		$rec->Find( $query );
		if (!$rec) {
			return [];
		}
		$out = [];
		foreach ($rec as $el) {
			$e2 = $el;
			unset( $e2["referrer_text"] );
			$v = new ViewData( $e2 );
			self::RegisterText( $el["referrer"], $el["referrer_text"] );
			$out[] = $v;
		}

		return new ViewData( $rec );
	}

	/*
	=====================
	Count

	Counts views. $filter can be used to restrict rows used according to the
	style.

	$style is:
	* null - counts all rows matching the type and filter
	* "g1" - group results by subject
	* "g2" - group results by subject2

	You can set "*group" in the query to "subject" or "subject2" to count
	distinct values in that field.

	If you set "*fetch" in the query, you must explicitly fetch the count(s)
	you want as well.

	=====================
	*/
	public static function Count( $type, $style, $filter = null )
	{
		$rec = new SqlShadow( "views" );
		$query = [];
		$filter = $filter ? (array)$filter : [];
		$filter["type"] = $type;
		$query["*filter"] = $filter;
		switch ($style) {
		case "g1":
			$query["*group"] = "subject";
			$query["*fetch"] = ["subject","count of * as count"];
			$got = $rec->Find( $query );
			if (!$got || !count( $got )) {
				$got = [];
			} else {
				$got = array_combine( 
					array_column( $got, "subject"),
					array_column( $got, "count" )
				);
			}
			break;
		case "g2":
			$query["*group"] = "subject2";
			$query["*fetch"] = ["subject2","count of * as count"];
			if (!$got || !count( $got )) {
				$got = [];
			} else {
				$got = array_combine( 
					array_column( $got, "subject2"),
					array_column( $got, "count" )
				);
			}
			break;
		default:
			if (!$got || !count( $got )) {
				$got = 0;
			} else {
				$got = $got[0]["count"];
			}
			break;
		}
		return $got;
	}


	/*
	=====================
	Find

	Finds views
	=====================
	*/
	public static function Find( $query )
	{
		$rec = new SqlShadow( "views" );
		if (is_string( $query )) {
			$query = [ "subject" => $query ];
		} else if (is_object( $query )) {
			$query = (array) $query;
		}
		$query[ "*join" ] = [
			 "table" => "viewtext"
			,"on" => [ "vtid" => ["views.referrer"] ]
			,"fetch" => "text as referrer_text"
		];
		$query[ "*order" ] = ">time";

		$got = $rec->Find( $query );
		if (!$got) {
			return [];
		}
		$out = [];
		foreach ($got as $el) {
			$e2 = clone $el;
			self::RegisterText( $e2->referrer, $e2->referrer_text );
			unset( $e2->referrer_text );
			$v = new ViewData( $e2 );
			$out[] = $v;
		}

		return $out;
	}


	/*
	=====================
	Create

	Creates a new view.
	=====================
	*/
	public static function Create( $type, $subject, $time = null )
	{
		$rec = new SqlShadow( "views" );
		$rec->type = $type;
		$rec->subject = $subject;
		$rec->time = $rec->DateTime( ($time ? $time : microtime( true )) );
		if (!$rec->Flush()) {
			throw new \Exception( "unable to create view" );
		}
		return new ViewData( $rec );
	}

	/*
	=====================
	Load

	Gets a ViewData instance for the given id.
	=====================
	*/
	public static function Load( $viewid )
	{
		$rec = new SqlShadow( "views", [ "viewid" => $viewid ] );
		if (!$rec->Load()) {
			throw new \Exception( "unable to load view $viewid" );
		}
		return new ViewData( $rec );
	}
}
