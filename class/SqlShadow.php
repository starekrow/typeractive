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
			$this->allDirty = true;
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
		$this->allDirty = true;
		if ($this->def->autoindex) {
			unset( $this->data[ $this->def->autoindex ] );
		}
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
	*/
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
	*/
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
	*/
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
	*/
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
	=====================
	*/
	public function Load( $index = null )
	{
		$db = Sql::AutoConnect();
		$qtbl = $db->QuoteName( $this->table );
		$ai = $this->def->autoindex;
		if ($index === null) {
			$index = $this->data;
		}
		if (is_string( $index ) && $ai) {
			$index = [ "$ai" => $index ];
			$sql = $this->GetAutoIndexLoadSql();
		} else if (is_array( $index ) || is_object( $index )) {
			$index = (array)$index;
			if (isset( $index[ $ai ])) {
				$index = [ "$ai" => $index[ $ai ] ];
				$sql = $this->GetAutoIndexLoadSql();
			} else {
//				$index = $this->GetIndexKeys( $index );
				$sql = $this->CalcLoadSql( $index );
			}
		}
		if (!$index || !$sql) {
			return false;
		}
		$sql = "SELECT * FROM $qtbl $sql";
		$db = Sql::AutoConnect();
		$got = $db->query( $sql, $index );
		$this->isNew = false;
		$this->allDirty = false;
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
	=====================
	*/
	public function Find( $fields = null, $options = null )
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
	Update
	=====================
	*/
	public function Update()
	{
		$ai = $this->def->autoindex;
		$d = $this->data;
		if (isset( $d[ $ai ] )) {
			if (!$this->allDirty) {
				unset( $this->dirty[ $ai ] );
				if (count( $this->dirty ) == 0) {
					return 0;
				}
			}
			$db = Sql::AutoConnect();
			$qtbl = $db->QuoteName( $this->table );
			// hmm? $this->FillDefaults();
			$sql = "UPDATE $qtbl SET ";
			$stitch = "";
			$args = [ "$ai" => $this->data[ $ai ] ];
			foreach ($d as $col => $v) {
				if ($col == $ai || 
					(!$this->allDirty && empty($this->dirty[ $col ]))) {
					continue;
				}
				$qcol = $db->QuoteName( $col );
				$args[ $col ] = $v;
				$sql .= "$stitch$qcol=:$col";
				$stitch = ",";
			}
			$qai = $db->QuoteName( $ai );
			$sql .= " WHERE $qai=:$ai";
			$got = $db->query( $sql, $args );
			if ($got !== false) {
				$this->dirty = [];
				$this->allDirty = false;
			}
			return $got;
		}
		// TODO: update with other index
		return false;
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
		if (!isset( $this->data[ $ai ] )) {
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
        return new ArrayIterator( $this->data );
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

