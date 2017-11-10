<?php /* Copyright (C) 2017 David O'Riva. All rights reserved.
       ********************************************************/

function is_assoc($arr)
{
	if (!is_array( $arr ) || array() === $arr) {
		return false;
	}
	return array_keys($arr) !== range(0, count($arr) - 1);
}

if (!function_exists( "random_bytes" )) {
	function random_bytes( $count ) {
		return openssl_random_pseudo_bytes( $count );
	}
}
