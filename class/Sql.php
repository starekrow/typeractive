<?php /* Copyright (C) 2017 David O'Riva. MIT License.
       ********************************************************/
namespace Typeractive;

/*
================================================================================

Sql

Represents a connection to a SQL-style database. 

There is a query-building interface that is the preferred approach.
It guarantees correct formulation of queries at the expense of some 
flexibility. Injection attacks are ugly; use the provided parameterization 
whenever possible. It's mostly built on PDO support and should be solid.

There is also a static interface and a functional interface that you can use
if your implementation has the notion of a "default" database. Set this up by
creating a working connection and passing it to Sql::SetDefaultConnection().

================================================================================
*/

class Sql
{
	public $connected;				// current best guess

	/*
	=====================
	QuoteString
	=====================
	*/
	function QuoteString( $str )
	{
		return $this->db->quote( $str );
	}

	/*
	=====================
	QuoteName
	=====================
	*/
	function QuoteName( $name )
	{
	    return "`" . str_replace("`", "``", $name) . "`";
	}

	protected $db;
	public $errorCode;
	public $error;

	protected static $autoInstance;
	protected static $autoConfig;

	/*
	=====================
	AutoConnect
	=====================
	*/
	public static function AutoConnect( $instance = null )
	{
		if ($instance) {
			self::$autoInstance = $instance;
		}
		if (!$instance) {
			$instance = self::$autoInstance = new Sql();
		}
		if (!$instance->connected && self::$autoConfig) {
			$c = self::$autoConfig;
			if (!$instance->Connect( 
				 $c[ 'username' ]
				,$c[ 'password' ]
				,$c[ 'host' ]
				,$c[ 'port' ]
			)) {
				error_log( "DB Connection failed" );
				return $instance;
			}
			if ($c[ 'encoding' ]) {
				$instance->SetPreferredEncoding( $c[ 'encoding' ] );
			}
			if ($c[ 'database' ]) {
				$instance->UseDatabase( $c[ 'database' ] );
			}
		}
		return $instance;
	}

	/*
	=====================
	AutoConfig
	Sets parameters to be used in a future call to AutoConnect
	=====================
	*/
	public static function AutoConfig( $config )
	{
		if (!$config) {
			self::$autoConfig = null;
			return;
		}
		self::$autoConfig = [
			 'username' => ''
			,'password' => ''
			,'host' => null
			,'port' => null
			,'encoding' => null
			,'database' => null
		];
		foreach ($config as $k => $v) {
			self::$autoConfig[ $k ] = $v;
		}
	}

	/*
	=====================
	Connect
	=====================
	*/
	function Connect( $user, $password, $host = null, $port = null )
	{
		global $db_obj;
		$this->ClearErrors();
		try {
			if (!$host) {
				$host = "localhost";
			}
			if (!$port) {
				$port = 3306;
			}
			$db = new \PDO(
				"mysql:host=$host;port=$port",
				$user,
				$password
			);
			if (!$db) {
				return $this->Error( 79, "open failed with no explanation" );
			}
			$this->db = $db;
			$this->connected = true;
		} catch( \PDOException $e ) {
			return $this->Error( 79, $e->getMessage() );
		}
		return true;
	}

	/*
	=====================
	UseDatabase
	=====================
	*/
	public function UseDatabase( $dbname )
	{
		return $this->RunQuery( "USE " . $this->QuoteName( $dbname ) );
	}


	/*
	=====================
	SetPreferredEncoding

	Indicates how you like to see strings. Some databases will automatically
	translate responses from one encoding to another for you.
	=====================
	*/
	public function SetPreferredEncoding( $encoding )
	{
		return $this->RunQuery( "SET NAMES " . $this->QuoteName( $encoding ) );
	}

	const Q_ROW_COUNT 		= 1;
	const Q_SINGLE 			= 2;
	const Q_ROW 			= 3;
	const Q_COLUMN 			= 4;
	const Q_INSERT_ID		= 5;
	const Q_ROWS	 		= 6;
	const Q_TYPE_MASK		= 0x00ff;
	const Q_NUMBERED 		= 0x1000;
	const Q_CACHE 			= 0x2000;

	protected $stcache = [];
	/*
	=====================
	RunQuery
		returns array of associative arrays of results, or
		FALSE if there were no results, or
		an error object
	=====================
	*/
	protected function RunQuery( $stmt, $args = null, $type = self::Q_ROWS ) {
		$this->ClearErrors();

		error_log( $stmt );

		$good = true;
		if (!empty( $this->stcache[ $stmt ] )) {
			$ps = $this->stcache[ $stmt ];
			$good = $ps->execute( $args );
			$s = $ps;
		} else if ($args || ($type & self::Q_CACHE)) {
			$ps = $this->db->prepare( $stmt );
			if (!$ps) {
				$this->PDOCheckError( $this->db );
				if (!$this->error) {
					$this->Error( -1, "Unable to prepare statement" ); 
				}
				return false;
			}
			if ($type & self::Q_CACHE) {
				$this->stcache[ $stmt ] = $ps;
			}
			$good = $ps->execute( $args );
			$s = $ps;
		} else {
			$s = $this->db->query( $stmt );
		}
		if ($s === false) {
			$this->PDOCheckError( $this->db );
			if (!$this->error) {
				$this->Error( -1, "Unable to run query" ); 
			}
			return false;
		} else if (!$good) {
			$this->PDOCheckError( $s );
			if ($this->error) {
				return false;			
			}
		}
		switch ($type & self::Q_TYPE_MASK) {
		case self::Q_ROW_COUNT:
			$res = $s->rowCount();
			break;
		
		case self::Q_SINGLE:
			$res = $s->fetch( \PDO::FETCH_NUM );
			if( $res === FALSE )
				return FALSE;
			$res = $res[0];
			break;
		
		case self::Q_ROWS:
			if ($type & self::Q_NUMBERED) {
				$res = $s->fetchAll( \PDO::FETCH_NUM );
			} else {
				$res = $s->fetchAll( \PDO::FETCH_OBJ );
			}
			break;

		case self::Q_ROW:
			if ($type & self::Q_NUMBERED) {
				$res = $s->fetch( \PDO::FETCH_NUM );
			} else {
				$res = $s->fetch( \PDO::FETCH_OBJ );
			}
			break;

		case self::Q_INSERT_ID:
			$res = $this->db->lastInsertId();
			break;

		default:
			return $this->Error( 72, "Unknown query type " . $type );
		}
		return $res;
	}

	/*
	=====================
	query
	returns array of objects of results, OR
	false on error
	=====================
	*/
	function query( $stmt, $args = null ) 
	{
		return $this->RunQuery( $stmt, $args, self::Q_ROWS );
	}

	/*
	=====================
	queryN
	returns array of regular arrays of results, OR
	false on error
	=====================
	*/
	function queryN( $stmt, $args = null ) 
	{
		return $this->RunQuery( $stmt, $args, self::Q_ROWS | self::Q_NUMBERED );
	}

	/*
	=====================
	query1
	returns the first column of the first row of results, OR
	false on error
	=====================
	*/
	function query1( $stmt, $args = null )
	{
		return $this->RunQuery( $stmt, $args, self::Q_SINGLE );
	}

	/*
	=====================
	exec
	returns number of rows affected, or false if db reported an error
	=====================
	*/
	function exec( $stmt, $args = null )
	{
		return $this->RunQuery( $stmt, $args, self::Q_ROW_COUNT );
	}

	/*
	=====================
	execI
	returns last insert ID, or false if db reported an error
	=====================
	*/
	function execI( $stmt, $args = null )
	{
		return $this->RunQuery( $stmt, $args, self::Q_INSERT_ID );
	}

	/*
	=====================
	insert
	=====================
	*/
	function insert( $query )
	{
		$table = null;
		$rows = null;
		$fields = null;
		$rtype = self::Q_INSERT_ID;

		foreach ($query as $k => $v) {
			switch ($k) {
			case "row":
				if (is_assoc( $v ) || is_object( $v )) {
					if ($rows || $fields) {
						$err = "query->row conflicts";						
					}
					$fields = array_keys( $v );
					$rows = [ array_values( $v ) ];				
				} else if (is_array( $v )) {
					if ($rows) {
						$err = "query->row conflicts";
					}
					$rows = [ $v ];
				} else {
					$err = "bad value in query->row";
				}
				break;

			case "table":
				if (is_string( $v )) {

				} else {
					$err = "Bad value for query->table";
				}
				break;

			case "rows":
				if (is_array( $v ) && !is_assoc( $v )) {
					if ($rows) {
						$err = "query->rows conflicts";						
					}
					$rows = $v;
				} else {
					$err = "bad value in query->rows";
				}
				break;

			case "fields":
				if (is_array( $v ) && !is_assoc( $v )) {
					if ($fields) {
						$err = "query->fields conflicts";						
					}
					$fields = $v;
				} else {
					$err = "bad value in query->fields";
				}
				break;

			case "return_id":
				$rtype = self::Q_INSERT_ID;
				break;

			case "return_count":
				$rtype = self::Q_ROW_COUNT;
				break;

			default:
				$err = "Unknown insert entry $k";
				break;
			}
		}
		if (!$table || !$rows || !$fields) {
			$err = "Missing query information";
		} else if (!count( $fields )) {
			$err = "Empty fields list";
		} else if (!count( $rows )) {
			$err = "Empty rows list";
		} else {
			$c = count( $fields );
			$rc = count( $rows );
			for ($i = 0; $i < $rc; ++$i) {
				$r = $rows[ $i ];
				if (!is_array( $r ) || is_assoc( $r )) {
					$err = "Bad type in row $i";
					break;
				}
				if (count( $rows[ $i ] ) != $c) {
					$err = "Bad length in row $i";
					break;
				}
			}
		}
		if ($err) {
			return $this->Error( 71, $err );
		}

	}

	/*
	=====================
	QuoteTableColumn
	=====================
	*/
	function QuoteTableColumn( $colname )
	{
		$cn = explode( '.', $colname, 2 );
		if (count( $cn ) == 1) {
			return $this->QuoteName( $cn[0] );
		}
		return $this->QuoteName( $cn[0] ) . '.' . $this->QuoteName( $cn[1] );
	}

	/*
	=====================
	CompileFilterExpr_r
	=====================
	*/
	function CompileFilterExpr_r( $expr, $cn = null )
	{
		if (is_string( $expr )) {
			$e = $this->QuoteString( $expr );
			return $cn ? "$cn=$e" : "$e";
		} else if (is_bool( $expr )) {
			$e = $expr ? "TRUE" : "FALSE";
			return $cn ? "$cn=$e" : "$e";
		} else if (is_null( $expr )) {
			return $cn ? "$cn IS NULL" : "IS NULL";
		} else if (is_numeric( $expr )) {
			return $cn ? "$cn=$expr" : "$expr";
		} else if (is_array( $expr ) && !is_assoc( $expr )) {
			for ($i = 0; $i < count( $expr ); ++$i) {

			}
		} else {
			$this->Error( 71, "Invalid expression type" );
		}
	}

	/*
	=====================
	CompileFilter_r
	=====================
	*/
	function CompileFilter_r( $filter, $partial = false )
	{
		if (!$filter) {
			return "1";
		}
		$grp = [];
		if (is_assoc( $filter ) || is_object( $filter )) {
			$combine = " AND ";
			foreach ($filter as $fld => $constrain) {
				$fld = $this->QuoteTableColumn( $fld );
				$parts = [];
				if (is_array( $constrain ) && !is_assoc( $constrain )) {

				} else if (is_string( $constrain )) {

				} else if (is_numeric( $constrain )) {

				} else if (is_bool( $constrain )) {

				} else if (is_null( $constrain )) {

				}
			}
			// AND

		} else if (is_array( $filter )) {
			$combine = " OR ";
			if (!count( $filter )) {
				return "1";
			}
			foreach ($filter as $f) {
				$grp[] = "(" . $this->CompileFilter_r( $f ) . ")";
			}
		}
		return implode( $combine, $grp );
	}

	/*
	=====================
	CompileQuery
	=====================
	*/
	function CompileQuery( $query )
	{
		$table = null;
		$filters = null;
		$fields = null;
		$limit = null;
		$start = null;
		$order = null;
		$rev_order = false;
		$rtype = self::Q_INSERT_ID;
		$as = null;

		foreach ($query as $k => $v) {
			switch ($k) {
			case "filter":
				if (is_assoc( $v ) || is_object( $v )) {

				} else {
					$err = "bad value in query->filter";
				}
				break;

			case "table":
				if (is_string( $v )) {

				} else {
					$err = "Bad value for query->table";
				}
				break;

			case "limit":
				if (is_int( $v )) {
					$limit = $v;
				} else {
					$err = "bad value in query->limit";
				}
				break;

			case "start":
				if (is_int( $v )) {
					$limit = $v;
				} else {
					$err = "bad value in query->start";
				}
				break;

			case "as":
				if (is_string( $v )) {
					$as = $v;
				} else {
					$err = "bad value in query->as";
				}
				break;

			case "order":
				if (is_string( $v )) {
					if ($order) {
						$err = "query->order conflicts";
					}
					$order = [ $v ];
				} else if (is_array( $v ) && !is_assoc( $v )) {
					if ($order) {
						$err = "query->order conflicts";
					}
					$order = $v;
				} else {
					$err = "bad value in query->orer";
				}
				break;

			case "reverse_order":
				if (is_bool( $v )) {
					$rev_order = $v;
				} else if (is_string( $v )) {
					if ($order) {
						$err = "query->reverse_order conflicts";
					}
					$order = [ $v ];
				} else if (is_array( $v ) && !is_assoc( $v )) {
					if ($order) {
						$err = "query->reverse_order conflicts";
					}
					$order = $v;
				} else {
					$err = "bad value in query->reverse_order";
				}
				break;

			case "fields":
				if (is_array( $v ) && !is_assoc( $v )) {
					if ($fields) {
						$err = "query->fields conflicts";						
					}
					$fields = $v;
				} else {
					$err = "bad value in query->fields";
				}
				break;

			case "return":
				if ($v == "rows") {
					$rtype = self::Q_ROWS;
				} else if ($v == "column") {
					$rtype = self::Q_COLUMN;
				} else if ($v == "row") {
					$rtype = self::Q_ROW;
				} else if ($v == "value") {
					$rtype = self::Q_SINGLE;
				} else {
					$err = "invalid query->return value";
				}
				break;

			default:
				$err = "Unknown insert entry $k";
				break;
			}
		}
		if (!$table || !$fields) {
			$err = "Missing query information";
		} else if (!count( $fields )) {
			$err = "Empty fields list";
		} else if ($limit !== null && $limit <= 0) {
			$err = "Invalid limit";
		} else if ($start !== null && $start < 0) {
			$err = "Invalid start";
		} 
		if ($err) {
			return $this->Error( 71, $err );
		}
		$parts = [];

		if (!$filters) {
			$where = "WHERE 1";
		} else {
			$where = "";
			foreach ($filters as $k => $v) {

			}
		}
		if ($err) {
			return $this->Error( 71, $err );
		}
		$parts[] = "SELECT FROM";
		$parts[] = $this->QuoteName( $table );
		if ($as) {
			$parts[] = "AS ";
			$parts[] = $this->QuoteName( $as );
		}
		if ($join) {
			// ...
		}
				/*
		if (!$filters) {
			$parts[] = "WHERE 1";
		} else {
			$stk = [ $filters ];
			while (count( $stk )) {
				$grp = [];
				$fl = array_pop( $stk );
				if (is_assoc( $filt ) || is_object( $filt )) {
					foreach ($fl as $fn => $v) {
						if (is_array( $fl ) && !is_assoc)
					}
					// AND

				} else if (is_array( $fl )) {
					// OR
				} else {
					$err = "Invalid value in query->filters";
				}
			}
			foreach ($filters as $k => $v) {

			}
		}
		*/
	}

	/*
	=====================
	TableExists
	=====================
	*/
	function TableExists($table)
	{
		$res = $this->query1(
			"SELECT COUNT(*) FROM information_schema.tables 
			 WHERE table_schema=DATABASE() AND table_name=?",
			 $table );
		return $res == 1;
	}

	/*
	=====================
	db_column_exists
	=====================
	*/
	function ColumnExists( $table, $column )
	{
		$res = $this->query1(
			"SELECT COUNT(*) 
			   FROM information_schema.columns 
			   WHERE table_schema=DATABASE() 
			     AND table_name=? AND column_name=?",
			$table, $column );
		return $res == 1;
	}

	/*
	=====================
	ClearErrors
	=====================
	*/
	function ClearErrors()
	{
		$this->errorCode = 0;
		$this->error = null;
	}

	/*
	=====================
	Error
	=====================
	*/
	protected function Error( $code, $msg = false ) {
		if (!$code) {
			$this->errorCode = -1;
			$this->error = $msg;
			return;
		}
		$this->errorCode = $code;
		$this->error = ($msg === false) ? "DB Error " . $code : $msg;
		error_log( $this->error );
		return false;
	}

	/*
	=====================
	PDOCheckError
	=====================
	*/
	protected function PDOCheckError( $el ) {
		$r = array( 0, 73, "Cannot get error info" );
		if (!$this->db) {
			$r = array( 0, 73, "Database not connected" );
		} else {
			try {
				$r = $el->errorInfo();
			} catch( \PDOException $e ) {}
		}
		if( count( $r ) < 3 ) {
			if( $r[0]=='00000' || substr($r,0,2)=="01" ) {
				return false;
			}
			$r = array( 0, 79, $r[0] );
		}
		$this->Error( $r[1], $r[2] );
		return true;
	}
}
