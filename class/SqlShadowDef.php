<?php /* Copyright (C) 2017 David O'Riva. MIT License.
       ********************************************************/
namespace Typeractive;

/*
================================================================================

SqlShadowDef

Stores and maintains a description of a SQL database table for use with 
SqlShadow instances.
================================================================================
*/

class SqlShadowDef
{
	public $autoindex;
	public $autoindexQuery;
	public $index;
	public $indexKeys;
	public $indexQuery;
	public $defaults;
	public $columns;
	public $protected;
	function __construct( $def = null )
	{
		if (!$def) {
			return;
		}
		foreach ($def as $k => $v) {
			if (property_exists( $this, $k )) {
				$this->$k = $v;
			}
		}
	}
	protected static $emptyDef;
	public static function GetEmpty()
	{
		return clone self::$emptyDef;
	}
	public static function StaticInit()
	{
		self::$emptyDef = new SqlShadowDef();
	}
	public function Alter( $prop = null, $val = null )
	{
		$use = $this;
		if ($this->protected) {
			$use = clone $this;
			$use->protected = false;
		}
		if ($prop) {
			$use->$prop = $val;
			if ($prop == "index") {
				$this->indexKeys = null;
				$this->indexQuery = null;
			}
			if ($prop == "autoindex") {
				$this->autoindexQuery = null;
			}
		}
		return $use;
	}
}
SqlShadowDef::StaticInit();

