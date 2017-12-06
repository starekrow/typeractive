<?php /* Copyright (C) 2017 David O'Riva. MIT License.
       ********************************************************/
namespace Typeractive;

/*
================================================================================

SqlShadow

Magical-ish shadowing of a row. Supports:
 * delayed updates
 * creation
 * row templates
 * smart flushing

Start by invoking either "Select" or "Create". Neither will actually perform
the database operation until later.
================================================================================
*/

class SqlShadow implements 
	\ArrayAccess, \Countable, \JsonSerializable, \IteratorAggregate
{
	protected $table;

	protected $data;
	protected $dirty;
	protected $allDirty;
	protected $isNew;
	protected $def;
	protected $db;

	protected static $tableDefs = [];

	/*
	=====================
	__construct

	Examples:
	new record in autoindexed row:
		new SqlShadow( 'mytable' )
	load a new or existing record:
		new SqlShadow( 'mytable', [ "stuff" => 1, "blah" => "foo" ] )

	If data is supplied at construction, the assumption is that the row 
	already exists in the database. Use MarkDirty() to force a write.
	Otherwise, the instance is assumed to represent a new record and will
	try to insert itself when saved.
	=====================
	*/
	public function __construct( $table, $data = null )
	{
		$this->table = $table;
		if (!empty( self::$tableDefs[ $table ] )) {
			$this->def = self::$tableDefs[ $table ];
		} else {
			$this->def = SqlShadowDef::GetEmpty();
		}
		if ($data) {
			$this->data = (array) $data;
		} else {
			$this->data = [];
			$this->isNew = true;
		}
	}

	/*
	=====================
	ParseDateTime

	Parses the given SQL-style DATETIME value
	=====================
	*/
	public function ParseDateTime( $val )
	{
		if ($val === null) {
			return $val;
		}
		return strtotime( $val );
	}

	/*
	=====================
	DateTime

	Given a timestamp, returns a suitable DATETIME value
	=====================
	*/
	public function DateTime( $val = null )
	{
		return date("Y-m-d H:i:s", $val !== null ? $val : time() );
	}


	/*
	=====================
	MarkDirty
	=====================
	*/
	public function MarkDirty( $col = null )
	{
		if ($col === null) {
			foreach ($this->data as $k => $v) {
				$this->dirty[ $k ] = true;
			}
		} else {
			$this->dirty[ $col ] = true;
		}
		return $this;
	}

	/*
	=====================
	MarkNew
	=====================
	*/
	public function MarkNew()
	{
		if ($this->def->autoindex) {
			unset( $this->data[ $this->def->autoindex ] );
		}
		$this->dirty = [];
		$this->MarkDirty();
		$this->isNew = true;
		return $this;
	}


	/*
	=====================
	DefineTable

	Provide default index and column definitions for a table. This prevents
	having to set up each shadow individually.

	`def` should be an array or object with zero or more of the following
	keys:
	  * autoindex - the name of an autoincrement primary key column
	  * index - an array containing column names which, in aggregate, form a
	    "sufficiently unique" index for update operations. This is not needed
	    if autoindex is present.
	  * columns - a list of all column names in the table. 
	  * defaults - default values to assign to some or all columns

	Note the definition will completely replace any existing definition.
	=====================
	*/
	public static function DefineTable( $table, $def )
	{
		$d = new SqlShadowDef( $def );
		$d->protected = true;
		self::$tableDefs[ $table ] = $d;
	}

	/*
	=====================
	AlterDefinition
	=====================
	*/
	public function AlterDefintion( $prop, $val )
	{
		$this->def = $this->def->Alter( $prop, $val );
		return $this;
	} 


	/*
	=====================
	SetAutoIndex

	Set the name of an autoincrement primary key column. This allows easy,
	automatic handling of creation and updates.
	=====================
	*/
	public function SetAutoIndex( $field )
	{
		return $this->AlterDefinition( 'autoindex', $field );
	}

	/*
	=====================
	SetIndex

	Set the name(s) of a sufficiently unique row index.
	This will be used for updates. If not specified, the original values of ALL 
	fields are used to select for updates, which may increase the chance of a 
	failed update.

	This will replace any existing index definition.
	=====================
	*/
	public function SetIndex( $fields )
	{
		if (is_string( $fields )) {
			if ($fields == "") {
				$fields = null;
			} else {
				$fields = [ $fields ];
			}
		} else if (is_array( $fields )) {
			if (count( $fields ) == 0) {
				$fields = null;
			}
		} else {
			// TODO: throw?
			$fields = null;
		}
		return $this->AlterDefinition( 'index', $field );
	}

	/*
	=====================
	GetIndexKeys
	=====================
	* /
	public function GetIndexKeys( $vals = null )
	{
		$d = $this->def;
		if (!$d->index) {
			return false;
		}
		if (!$d->indexKeys) {
			$d->indexKeys = array_fill_keys( $d->index, true );
		}
		if (!$vals) {
			return $d->indexKeys;
		}
		$iv = array_intersect_keys( $vals, $d->indexKeys );
		if (count( $iv ) != count( $d->index )) {
			return false;
		}
		return $iv;
	}

	/*
	=====================
	GetAutoIndexLoadSql
	=====================
	* /
	public function GetAutoIndexLoadSql()
	{
		if ($this->def->autoindexQuery) {
			return $this->def->autoindexQuery;
		}

		$db = Sql::AutoConnect();
		$ai = $this->def->autoindex;
		$qtbl = $db->QuoteName( $this->table );
		$qai = $db->QuoteName( $ai );
		$sql = "WHERE $qai=:$ai";
		$this->def->autoindexQuery = $sql;
		return $sql;
	}

	/*
	=====================
	GetIndexLoadSql
	=====================
	* /
	public function GetIndexLoadSql()
	{
		if ($this->def->indexQuery) {
			return $this->def->indexQuery;
		}
		$db = Sql::AutoConnect();
		$il = $this->def->index;
		$qtbl = $db->QuoteName( $this->table );
		$sql = "";
		$stitch = ' WHERE ';
		foreach ($this->def->index as $col) {
			$qcol = $db->QuoteName( $col );
			$sql .= $stitch . $qcol . "=:$col";
			$stitch = ' AND '; 
		}
		$this->def->indexQuery = $sql;
		return $sql;
	}

	/*
	=====================
	CalcLoadSql
	=====================
	* /
	public function CalcLoadSql( $cols, $limit = null, $offset = null )
	{
		$db = Sql::AutoConnect();
		$qtbl = $db->QuoteName( $this->table );
		$sql = "";
		$stitch = ' WHERE ';
		foreach ($cols as $col => $val) {
			$qcol = $db->QuoteName( $col );
			$sql .= $stitch . $qcol . "=:$col";
			$stitch = ' AND '; 
		}
		// TODO: limit, offset
		return $sql;
	}

	/*
	=====================
	Load

	Loads a single record with the given query. If no query is supplied, or the
	query's "*filter" property is empty, uses the current values of the data 
	fields as the filter.

	Note that in the case where the data fields are used, ALL defined fields
	are used to construct the query. If you want to reload using only an index
	and some other fields have been set, you must unset any non-index fields.
	=====================
	*/
	public function Load( $query = null )
	{
		if (is_object( $query )) {
			$query = (array) $query;
		}
		if (!$query) {
			$query = [];
			if (!count( $this->data )) {
				return false;
			}
			foreach ($this->data as $k => $v) {
				$query[$k] = $v;
			}
		} else if (is_array( $query )) {
			if (!count( $query )) {
				return false;
			}
			$query["*limit"] = 1;
		}

		$sql = $this->CompileQuery( $query, $args ); 
		$got = $this->db->query( $sql, $args );
		$this->isNew = false;
		$this->dirty = [];
		if (!$got) {
			return false;
		}
		foreach ($got[0] as $k => $v) {
			$this->data[ $k ] = $v;
		}
		return $got;
	}

	/*
	=====================
	Delete
	=====================
	*/
	public function Delete( $fields = null, $options = null )
	{
		$db = Sql::AutoConnect();
		$qtbl = $db->QuoteName( $this->table );
		$ai = $this->def->autoindex;
		if ($fields === null) {
			$fields = $this->data;
		}
		if (is_string( $fields ) && $ai) {
			$fields = [ "$ai" => $fields ];
			$sql = $this->GetAutoIndexLoadSql();
		} else if (is_array( $fields ) || is_object( $fields )) {
			$fields = (array)$fields;
			if (isset( $fields[ $ai ])) {
				$fields = [ "$ai" => $fields[ $ai ] ];
				$sql = $this->GetAutoIndexLoadSql();
			} else {
//				$fields = $this->GetIndexKeys( $fields );
				$sql = $this->CalcLoadSql( $fields );
			}
		}
		if (!$fields || !$sql) {
			return false;
		}
		$sql = "SELECT * FROM $qtbl $sql";
		$db = Sql::AutoConnect();
		$got = $db->query( $sql, $fields );
		if (!$got) {
			return false;
		}
		$vals = [];
		foreach ($got as $row) {
			$vals[] = new SqlShadow( $this->table, $row );
		}
		return $vals;
	}


	/*
	=====================
	Find

	Finds matching records. Note that an empty query will return `false` under
	the assumption there was an error. To query all records, supply
	a valid query with an empty "*filter" property.
	=====================
	*/
	public function Find( $query )
	{
		if (is_object( $query )) {
			$query = (array) $query;
		}
		if (!$query) {
			$query = $this->data;
		}
		if (is_array( $query ) && !count( $query )) {
			return false;
		}
		$sql = $this->CompileQuery( $query, $args ); 
		$got = $this->db->query( $sql, $args );
		if (!$got) {
			return false;
		}
		$vals = [];
		foreach ($got as $row) {
			$vals[] = new SqlShadow( $this->table, $row );
		}
		return $vals;
	}

	/*
	=====================
	FillDefaults
	=====================
	*/
	public function FillDefaults()
	{
		if ($this->def->defaults) {
			foreach ($this->def->defaults as $k => $v) {
				if (!array_key_exists( $k, $this->data )) {
					$this->data[ $k ] = $v;
					$this->dirty[ $k ] = true;
				}
			}
		}
		return $this;
	}

	/*
	=====================
	CompileValue
	=====================
	*/
	protected function CompileValue( $v, &$args )
	{
		if (is_array( $v )) {
			if (isset( $v[0] ) && count($v) == 1) {
				$cn = explode( ".", $v[0], 2 );
				$cn[0] = $this->db->QuoteName( $cn[0] );
				if (count( $cn ) == 2) {
					$cn[1] = $this->db->QuoteName( $cn[1] );
				}
				return implode( ".", $cn );
			}
			throw new AppError( "Invalid value" );
		}
		if (is_object( $v )) {
			$v = (string) $v;
		}
		$n = 'a__' . ($args['?argcounter']++);
		$args[ $n ] = $v;
		return ":$n";
	}

	/*
	=====================
	CompileTest
	=====================
	*/
	protected function CompileTest( $subject, $test, &$args, $table = null )
	{
		if (!is_array( $test ) || count($test) == 1) {
			return $this->CompileTest( $subject, [ "=", $test ], $args );
		}
		$db = Sql::AutoConnect();
		$out = ["("];
		$ss = $table ? $db->QuoteName( $table ) . "." : "";
		$ss .= $db->QuoteName( $subject );
		$spacer = "";
		for ($i = 0; $i < count( $test ); ++$i) {
			$out[] = $spacer;
			$spacer = " ";
			$op = $test[ $i ];
			// no unary ops yet
			if ($i + 1 >= count( $test )) {
				throw new \Exception( "Missing args for test" );
				return false;
			}
			$v = $test[ $i + 1 ];
			++$i;
			switch ($op) {
			case "=";
				if ($v === null) {
					$out[] = "$ss IS NULL";
				} else {
					$out[] = "$ss $op " . $this->CompileValue( $v, $args );
				}
				break;
			case "!=";
				if ($v === null) {
					$out[] = "$ss IS NOT NULL";
				} else {
					$out[] = "$ss $op " . $this->CompileValue( $v, $args );
				}
				break;
			case "<";
			case ">";
			case "<=";
			case ">=";
				$out[] = "$ss $op " . $this->CompileValue( $v, $args );
				break;
			case "range";
				if ($i + 1 >= count( $test )) {
					throw new \Exception( "Missing args for range" );
				}
				$v2 = $test[ $i + 1 ];
				++$i;
				if ($v !== null) {
					$out[] = "$ss >= " . $this->CompileValue( $v, $args );
					if ($v2 !== null) {
						$out[] = " AND ";
					}
				}
				if ($v2 !== null) {
					$out[] = "$ss < " . $this->CompileValue( $v2, $args );
				} else if ($v1 === null) {
					$out[] = "1";
				}
				break;
			default:
				return false;
			}
			if ($i + 1 < count($test)) {
				if ($test[$i + 1] == "and") {
					$out[] = " AND ";
					++$i;
				} else if ($test[$i + 1] == "or") {
					$out[] = " OR ";
					++$i;
				} else /* implicit and */ {
					$out[] = " AND ";
				}
			}
		}
		$out[] = ")";
		return implode( "", $out );
	}

	/*
	=====================
	CompileFilter
	=====================
	*/
	protected function CompileFilter( $cond, &$args, $table = null )
	{
		if (!$cond || (is_array( $cond ) && !count( $cond ))) {
			return "1";
		} else if (is_array( $cond ) && !empty( $cond[0] )) {
			// multiple matches at top level
			$out = [];
			//var_dump( $cond );
			foreach ($cond as $k => $v) {
				$out[] = $this->CompileFilter( $v, $args, $table );
			}
			//var_dump( $out );
			return "(" . implode(") OR (", $out) . ")";
		}
		$tests = [];
		$table = $table ? $this->db->QuoteName( $table ) . "." : "";
		foreach ($cond as $k => $v) {
			if ($k[0] == "*") {
				continue;
			}
			$tests[] = $this->CompileTest( $k, $v, $args, $table );
		}
		return "(" . implode(") AND (", $tests ) . ")";
	}

	/*
	=====================
	CompileFetch
	=====================
	*/
	protected function CompileFetch( $f, $table = null )
	{
		$prefix = isset( $table ) ? $this->db->QuoteName( $table ) . "." : "";
		if ($f == "*") {
			return $prefix . "*";
		}
		$out = "";
		$f = is_array( $f ) ? $f : [ $f ];
		$stitch = "";
		foreach ($f as $el) {
			$p = explode( " as ", $el );
			$out .= $stitch;
			$stitch = ",";
			$colname = $prefix . $this->db->QuoteName( $p[0] );
			if (strpos( $p[0], "count of " ) === 0) {
				$colname = substr( $p[0], 9 );
				$colname = $prefix . $this->db->QuoteName( $colname );
				$colname = "COUNT($colname)"; 
			}
			if (count( $p ) == 1) {
				$out .= $colname;
			} else {
				$out .= "$colname AS " . $this->db->QuoteName( $p[1] );
			}
		}
		return $out;
	}

	/*
	=====================
	CompileQuery

	Query should be an array or object with the following properties:

	* `*filter` - fields to match
	  * array - set of alternate conditions (OR)
	  * associative array or object - fields to match (AND)
	  * field values -
	    * normal scalar - exact match
	    * array - [ op, val, ... ]. Operations:
	      * if val is an array, treat as column name instead of value
	      * "=", "!=", <=", ">=", "<", ">" - equality/relational
	      * "and", "or" - after first op/val, default is "and"
	      * "range" - follow by v1, v2, checks v1 <= field < v2
	      * "in" - val is array with alternatives
	    * array (1 element) - column name
	    * (TODO) assoc array
	      * field - match field name
	      * special - 
	        * "now" - DB timestamp
	      * expr - 
	      * args -
	* `*join` - join tables for results
	  * table - table name
	  * on - filter for join
	  * as - table nickname
	  * fetch - limit columns to fetch
	* `*fetch` - limit columns to read
	* `*limit` - maximum number of rows to return
	* `*offset` - skip this many rows
	* `*order` - column name or array of names. Prefix with "<" for ascending,
	  ">" for descending.

	If `*filter` is not present, the query itself is taken as the filter, with
	`*`-prefixed fields ignored.

	Returns a SQL query fragment, and sets or updates an argument container.
	=====================
	*/
	protected function CompileQuery( $query, &$args, $type = null )
	{
		if (!$this->db) {
			$this->db = Sql::AutoConnect();
		}
		$query = is_object( $query ) ? (array) $query : $query;
		if (is_string( $query )) {
			$query = [ "*filter" => $query ];
		}
		if (!empty( $query[0] ) && count( $query ) == 1) {
			if ($query == "*") {
				$query = [ "*filter" => [] ];
			} else {
				throw new \Exception( "Unrecognized special query" );
			}
		}

		if (!$args) {
			$args = [];
		}
		$args['?argcounter'] = 1;

		$table = $this->table;
		if (empty( $query[ "*as" ] )) {
			$tspec = $this->db->QuoteName( $this->table );
		} else {
			$tspec = $this->db->QuoteName( $this->table );
			$tspec .= " " . $this->db->QuoteName( $query[ "*as" ] );
			$table = $query[ "*as" ];
		}
		if (empty( $query[ "*fetch" ] )) {
			$fetch = $this->CompileFetch( "*", $table );
		} else {
			$fetch = $this->CompileFetch( $query[ "*fetch" ], $table );
		}
		if (!empty( $query[ "*join" ] )) {
			$jl = $query[ "*join" ];
			if (is_object( $jl )) {
				$jl = [ $jl ];
			} else if (!is_array( $jl )) {
				throw new \Exception( "Invalid join value" );
			} else if (!isset( $jl[0] )) {
				$jl = [ $jl ];
			}
			foreach ($jl as $j) {
				$j = is_object( $j ) ? (array)$j : $j;
				$tspec .= " JOIN " . $this->db->QuoteName( $j["table"] );
				$jt = $j["table"];
				if (!empty( $j["as"] )) {
					$tspec .= " " . $this->db->QuoteName( $j["as"] );
					$jt = $j["as"];
				}
				if (!empty( $j["on"] )) {
					$tspec .= " ON ";
					$tspec .= $this->CompileFilter( $j["on"], $args, $jt );
				}
				$fetch .= ",";
				if (empty( $j["fetch"] )) {
					$fetch .= $this->CompileFetch( "*", $jt );
				} else {
					$fetch .= $this->CompileFetch( $j[ "fetch" ], $jt );
				}
			}
		}

		$where = " WHERE ";
		if (isset( $query[ "*filter" ] )) {
			if (is_string( $query[ "*filter" ] )) {
				if (!$this->def->autoindex) {
					throw new \Exception( "No autoindex for string query" );
				}
				$ai = $this->def->autoindex;
				$where .= $this->CompileFilter( 
					["$ai" => $query[ "*filter" ] ], $args );

			} else {
				$where .= $this->CompileFilter( $query[ "*filter" ], $args );
			}
		} else {
			$where .= $this->CompileFilter( $query, $args );
		}

		$paging = "";
		if (!empty( $query["*group"] )) {
			$ol = $query["*group"];
			$ol = is_array($ol) ? $ol : [ $ol ];
			$paging .= " GROUP BY ";
			$stitch = "";
			foreach ($ol as $o) {
				$paging .= $stitch . $this->db->QuoteName( $o );
				$stitch = ",";
			}
		}
		if (!empty( $query["*order"] )) {
			$ol = $query["*order"];
			$ol = is_array($ol) ? $ol : [ $ol ];
			$paging .= " ORDER BY ";
			$stitch = "";
			foreach ($ol as $o) {
				$paging .= $stitch;
				$stitch = ",";
				if ($o[0] == "<") {
					$paging .= $this->db->QuoteName( substr( $o, 1 ) ) . " ASC";
				} else if ($o[0] == ">") {
					$paging .= $this->db->QuoteName( substr( $o, 1 ) ). " DESC";
				} else {
					$paging .= $this->db->QuoteName( $o );
				}
			}
		}
		if (!empty( $query["*limit"] )) {
			$paging .= " LIMIT ";
			if (!empty( $query["*offset"] )) {
				$paging .= ((int)$query["*offset"]) . ",";
			}
			$paging .= ((int)$query["*limit"]);
		} else if (isset( $query["*offset"] )) {
			$paging .= " LIMIT " . ((int)$query["*offset"]) . ",0";
		}
		unset( $args['?argcounter'] );

		if ($type == "d") {
			$tn = $this->db->QuoteName( $table );
			return "DELETE $tn FROM $tspec $where $paging";
		} else if ($type == "u") {
			return [
				 "table" => $tspec
				,"where" => $where
				,"paging" => $paging
			];
		}
		// default is select
		return "SELECT $fetch FROM $tspec $where $paging";
	}

	/*
	=====================
	Update
	=====================
	*/
	public function Update( $query = null )
	{
		$d = $this->data;
		if (count( $this->dirty ) == 0) {
			return 0;
		}
		if (!$query) {
			$ai = $this->def->autoindex;
			if ($ai && isset( $d[ $ai ] )) {
				$query = $d[ $ai ];
			} else {
				$query = [];
				foreach ($this->data as $k => $v) {
					if (empty( $this->dirty[ $k ] )) {
						$query[ $k ] = $v;
					}
				}
			}
		} else {
			$query = is_object( $query ) ? (array) $query : $query;
			if (!is_array( $query )) {
				return false;
			}
		}
		$cq = $this->CompileQuery( $query, $args, "u" );
		$sql = "UPDATE " . $cq['table'] . " SET ";

		$stitch = "";
		foreach ($d as $col => $v) {
			if (empty( $this->dirty[ $col ] )) {
				continue;
			}
			$qcol = $this->db->QuoteName( $col );
			$args[ $col ] = $v;
			$sql .= "$stitch$qcol=:$col";
			$stitch = ",";
		}

		$sql .= $cq['where'] . " " . $cq['paging'];
		$got = $this->db->exec( $sql, $args );
		if ($got !== false) {
			$this->dirty = [];
		}
		return $got;
	}

	/*
	=====================
	Insert
	=====================
	*/
	public function Insert()
	{
		$ai = $this->def->autoindex;
		$d = $this->data;

		$db = Sql::AutoConnect();
		$qtbl = $db->QuoteName( $this->table );
		$this->FillDefaults();
		$cols = [];
		$vals = [];
		$args = [];
		foreach ($d as $col => $v) {
			if ($col != $ai) {
				$cols[] = $db->QuoteName( $col );
				$vals[] = ":$col";
			}
			$args[ $col ] = $v;
		}
		$cols = implode( ",", $cols );
		$vals = implode( ",", $vals );
		$sql = "INSERT INTO $qtbl ($cols) VALUES ($vals)";
		if ($ai) {
			$got = $db->execI( $sql, $args );
		} else {
			$got = $db->exec( $sql, $args );			
		}
		if ($got !== false) {
			if ($ai) {
				$this->data[ $ai ] = $got;
			}
			$this->dirty = [];
			$this->allDirty = false;
			$this->isNew = false;
		}
		return $got;
	}

	/*
	=====================
	Flush
	=====================
	*/
	public function Flush()
	{
		if ($this->isNew) {
			return $this->Insert();
		}
		$ai = $this->def->autoindex;
		if ($ai && !isset( $this->data[ $ai ] )) {
			return $this->Insert();
		}
		return $this->Update();
	}

	/*
	----------------------------------------------------------------------------
	ArrayAccess interface
	----------------------------------------------------------------------------
	*/
	public function offsetSet( $offset, $value )
	{
		if( !is_null( $offset ) ) {
			if (!array_key_exists( $offset, $this->data )
		 		|| $this->data[ $offset ] !== $value) {
				$this->dirty[ $offset ] = true;
			}
			$this->data[ $offset ] = $value;
		}
	}
	public function offsetExists( $offset )
	{
		return array_key_exists( $offset, $this->data );
	}
	public function offsetUnset( $offset )
	{
		if (array_key_exists( $offset, $this->data )) {
			$this->dirty[ $offset ] = true;
		}
		unset( $this->data[ $offset ] );
	}
	public function offsetGet( $offset )
	{
		return array_key_exists( $offset, $this->data ) ? 
			$this->data[ $offset ] : null;
	}

	/*
	----------------------------------------------------------------------------
	Object property access overloads
	----------------------------------------------------------------------------
	*/
	public function __set( $offset, $value )
	{
		if( !is_null( $offset ) ) {
			if (!array_key_exists( $offset, $this->data )
		 		|| $this->data[ $offset ] !== $value) {
				$this->dirty[ $offset ] = true;
			}
			$this->data[ $offset ] = $value;
		}
	}
	public function __isset( $offset )
	{
		return isset( $this->data[ $offset ] );
	}
	public function __unset( $offset )
	{
		if (array_key_exists( $offset, $this->data )) {
			$this->dirty[ $offset ] = true;
		}
		unset( $this->data[ $offset ] );
	}
	public function __get( $offset )
	{
		return array_key_exists( $offset, $this->data ) ? 
			$this->data[ $offset ] : null;	
	}

	/*
	----------------------------------------------------------------------------
	Countable interface
	----------------------------------------------------------------------------
	*/
	public function count()
	{
		return count( $this->data );
	}

	/*
	----------------------------------------------------------------------------
	IteratorAggregate interface
	----------------------------------------------------------------------------
	*/
    public function getIterator()
    {
        return new \ArrayIterator( $this->data );
    }

	/*
	----------------------------------------------------------------------------
	JsonSerializable interface
	----------------------------------------------------------------------------
	*/
    public function jsonSerialize()
    {
        return (object) $this->data;
    }

}

