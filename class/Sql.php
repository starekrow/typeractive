<?php /* Copyright (C) 2017 David O'Riva. MIT License.
       ********************************************************/
namespace Typeractive;

/*
================================================================================

Sql

Represents a connection to a SQL-style database. 

There is an extensive query-building interface that is the preferred approach.
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
				return $instance;
			}
			if ($c[ 'encoding' ]) {
				$this->SetPreferredEncoding( $c[ 'encoding' ] );
			}
			if ($c[ 'database' ]) {
				$this->UseDatabase( $c[ 'database' ] );
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
				$pass,
				array( \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8" )
			);
			if (!$db) {
				return $this->Error( 79, "open failed with no explanation" );
			}
			$this->db = $db;
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
		return $this->ExecRaw( "USE " . $this->QuoteName( $dbname ) );
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
		return $this->ExecRaw( "SET NAMES " . $this->QuoteName( $encoding ) );
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

		if (!empty( $this->stcache[ $stmt ] )) {
			$ps = $this->stcache[ $stmt ];
			$s = $ps->execute( $args );
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
			$s = $ps->execute( $args );
		} else {
			$s = $this->db->query( $stmt );
		}
		if ($s === false) {
			$this->PDOCheckError( $this->db );
			if (!$this->error) {
				$this->Error( -1, "Unable to run query" ); 
			}
			return false;
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
				$res = $s->fetchAll( \PDO::FETCH_ASSOC );
			}
			break;

		case self::Q_ROW:
			if ($type & self::Q_NUMBERED) {
				$res = $s->fetch( \PDO::FETCH_NUM );
			} else {
				$res = $s->fetch( \PDO::FETCH_ASSOC );
			}
			break;

		case self::Q_INSERT_ID:
			$res = $this->db->lastInsertId();

		default:
			return $this->Error( 72, "Unknown query type " . $type );
		}
		return $res;
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
					$err = "bad value in query->row";
				}
				break;

			case "fields":
				if (is_array( $v ) && !is_assoc( $v )) {
					if ($fields) {
						$err = "query->fields conflicts";						
					}
					$fields = $v;
				} else {
					$err = "bad value in query->row";
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
