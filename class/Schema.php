<?php /* Copyright (C) 2017 David O'Riva. All rights reserved.
       ********************************************************/
/*
================================================================================

Schema - data structure validator

Known schema types:
  * <array> - matches first matching entry in array
  * <object> or <assoc> - match with auto-typing
    * otherwise, data must be an object or assoc and each key is matched, 
      *except* as follows:
      * "*" - applies to any otherwise unspecified keys
      * if a key name ends with "?", the key may optionally be missing or null
      * TODO: if a key name ends with "!", the "!" is stripped
  * <string> - 
    * "=<chars>" - exactly match <chars>
    * "~=<chars>" - case-insensitive match <chars>
    * "any" - data exists with any value, including null
    * "null" - data is null
    * "bool" - data is boolean
    * "int" - data is integer
    * "number" - data is numeric (not string!)
    * "string" - any string matches
    * "empty" - data is null or missing or "" or {} or []
    * "false" - data is boolean false
    * "falsy" - data is like boolean false - empty string, 0, null, false
    * "true" - data is boolean true
    * "truthy" - data is not falsy
    * "/regex/" - data matches regex
    * "|s1|s2...|" - data matches one of the given strings
  * <number> - exactly match the given number

TODO: Compile for faster validation
================================================================================
*/

class Schema
{
	public $def;

	/*
	=====================
	Validate

	Returns `true` if data follows the schema.
	Otherwise returns a string containing a description of what didn't match.
	=====================
	*/
	public static function Validate( $data, $s )
	{
		if (is_array( $s )) {
			if (!count( $s )) {
				// empty object
				if (!is_array( $data ) && !is_object( $data )) {
					return "NotDictLike";
				}
				if (count( $data ) != 0) {
					return "NotEmptyDictLike";
				}
				return true;
			} else if (!is_assoc( $s )) {
				foreach ($s as $el) {
					if (self::Validate( $data, $el ) === true) {
						return true;
					}
				}
				return "NoMatchingOption";
			}
		}
		if (is_object( $s ) || is_array( $s )) {
			$wc = false;
			$valid = [];

			if (is_object( $data )) {
				if ($data instanceof stdClass) {
					$c = (array) $data;
				} else if ($data instanceof Dict) {
					$c = $data->ToArray();
				} else {
					$c = [];
					foreach ($data as $k => $v) {
						$c[$k] = $v;
					}
				}
			} else if (is_array( $data )) {
				$c = $data;
			} else {
				return "NotDictLike";
			}
			foreach ($s as $k => $v) {
				$kl = strlen( $k );
				if ($kl && $k[ $kl - 1 ] == "?") {
					$k = substr( $k, 0, $kl - 1 );
					if (!array_key_exists( $k, $c )) {
						continue;
					}
				} else if ($kl && $k[0] == "!") {
					continue;
				} else {
					if (!array_key_exists( $k, $c )) {
						if ($k == "*") {
							continue;
						}
						return "MissingKey ($k)";
					}
				}
				$res = self::Validate( $c[ $k ], $v );
				if ($res !== true) {
					return "$k: $res";
				}
				unset( $c[ $k ] );
			}
			if (!count( $c )) {
				return true;
			}
			if (!empty( $s['*'] )) {
				$sr = $s['*'];
				if ($sr == "any") {
					return true;
				}
				foreach ($c as $k => $v) {
					$res = self::Validate( $v, $sr );
					if ($res !== true) {
						return "$k (*): $res";
					}
				}
				return true;
			}
			return array_shift( array_keys( $c ) ) . ": UnexpectedKey";
		}
		if (is_int( $s ) || is_float( $s )) {
			if (!is_int( $data ) || !is_float( $data )) {
				return "NotANumber";
			}
			if ($data != $s) {
				return "WrongValue";
			}
			return true;
		}
		if (!is_string( $s ) || strlen( $s ) == 0) {
			return "UnknownSchemaType";
		}
		$s0 = $s[0];
		if ($s0 == "=") {
			if (!is_string( $data )) {
				return "NotAString";
			}
			if (strcmp($data, substr($s, 1)) != 0) {
				return "WrongString";
			}
			return true;
		} else if ($s0 == "~" && $s[1] == "=") {
			if (!is_string( $data )) {
				return "NotAString";
			}
			if (strcasecmp($data, substr($s, 2)) != 0) {
				return "WrongString";
			}
			return true;
		} else if ($s0 == "|") {
			if (!is_string( $data )) {
				return "NotAString";
			}
			if ($s[ strlen( $s ) - 1 ] != "|") {
				return "BadMultiStringSchema";
			}
			$opts = explode( "|", $s );
			$opts = array_slice( $opts, 1, count( $opts ) - 2 );
			if (array_search( $data, $opts ) === false) {
				return "NotInList";
			}
			return true;
		} else if ($s0 == "/") {
			if (!is_string( $data )) {
				return "NotAString";
			}
			if (!preg_match($s, $data)) {
				return "NoPatternMatch";
			}
			return true;
		}
		// TODO numeric range } else if ($s0 == "#") {
		switch ($s) {
		case "any":
			return true;
		case "null":
			if ($data !== null) {
				return "NotNull";
			}
			return true;
		case "dict":
			if (!($data instanceof Dict)) {
				return "NotADict";
			}
			return true;
		case "dictlike":
			if (is_object( $data )) {
				if ($data instanceof Dict || $data instanceof stdClass) {
					return true;
				}
			} else if (is_assoc( $data )) {
				return true;
			}
			return "NotObjectLike";
		case "object!":
			if (!is_object( $data )) {
				return "NotAnObject";
			}
			return true;
		case "array":
			if (!is_array( $data ) || is_assoc( $data )) {
				return "NotAnArray";
			}
			return true;
		case "bool":
			if (!is_bool( $data )) {
				return "NotABool";
			}
			return true;
		case "false":
			if ($data !== false) {
				return "NotFalse";
			}
			return true;
		case "true":
			if ($data !== true) {
				return "NotFalse";
			}
			return true;
		case "empty":
			if ($data === null || $data === "" || $data === false) {
				return true;
			}
			if (is_object( $data ) && count( (array)$data ) == 0) {
				return true;
			}
			if (is_array( $data ) && count( $data ) == 0) {
				return true;
			}
			return "NotEmpty";
		case "number":
			if (!is_int( $data ) && !is_float( $data )) {
				return "NotANumber";
			}
			return true;
		case "int":
			if (!is_int( $data )) {
				return "NotAnInt";
			}
			return true;
		}
		return "UnknownSchemaType ($s)";
	}

	/*
	=====================
	__construct
	=====================
	*/
	public function __construct( $schema )
	{
		$this->def = $schema;
	}

	/*
	=====================
	Update
	=====================
	*/
	public function Update( $schema )
	{
		$this->def = $schema;
	}

	/*
	=====================
	Test

	Returns `true` if the data matches the schema, otherwise `false`.
	If you provide a variable for `reason`, it will be filled with a short
	explanation of why the schema didn't match.
	=====================
	*/
	public function Test( $data, &$reason = null )
	{
		$res = self::Validate( $data, $this->def );
		if ($res === true) {
			$reason = false;
			return true;
		}
		$reason = $res;
		return false;
	}
}

