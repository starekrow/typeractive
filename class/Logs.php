<?php /* Copyright (C) 2017 David O'Riva. MIT License.
       ********************************************************/
namespace Typeractive;

/*
================================================================================

Logs

Handling for log data

================================================================================
*/

class Logs
{
	const LOGROOT = "/tmp/typerlogs";

	/*
	=====================
	LogPath

	Determines the pathname for a given log file.
	=====================
	*/
	static function LogPath( $log )
	{
		$log = str_replace( "\\", "/", $log );
		if ($log[0] == "/" || $log[1] == ":") {
			return $log;
		}
		return self::LOGROOT . "/$log";
	}

	/*
	=====================
	Roll

	Moves the current contents of a log file to a new file. You are strongly 
	suggested to wait for a little bit (50ms-ish) after this function in case 
	any writes are pending.

	TODO: The destination filename may contain the following tokens:
	* {year}
	* {month}
	* {day}
	* {ymd}
	* {hour}
	* {min}
	* {sec}
	* {wd} - weekday
	* {hms}
	* {ms} - milliseconds, three digits
	* {us} - microseconds, six digits
	* {ctr} - counter, starts at 1, first unused value in directory is taken

	Returns the full pathname of the file containing the log data.

	Returns `false` if the log does not exist or is unavailable.
	=====================
	*/
	static function Roll( $log, $dest = null )
	{
		// TODO: try to take hard file lock?
		$fn = self::LogPath( $log );
		if (!is_file( $fn )) {
			return false;
		}
		if ($dest) {
			// TODO: tokens
			$tn = self::LogPath( $dest );
		} else {
			$tn = tempnam( self::LOGROOT, "rl_" );
		}
		if (!rename( $fn, $tn )) {
			error_log( "Failed to rename $fn to $tn" );
			unlink( $tn );
			return false;
		}
		return $tn;
	}

	/*
	=====================
	Put

	Should be atomic with short (<512 char) lines. Longer lines will work but
	may require sorting out by hand (so OK for debug but not much else).

	Takes care of creating any intermediate directories.
	=====================
	*/
	static function Put( $log, $text )
	{
		$fn = self::LogPath( $log );
		if (is_array( $text )) {
			$text[] = PHP_EOL;
			$text = implode( "", $text );
		} else {
			$text = (string)$text;
			if ($text[ strlen($text) - 1 ] != "\n") {
				$text .= PHP_EOL;
			}
		}
		if (!file_put_contents( $fn, $text, FILE_APPEND )) {
			$dn = dirname( $fn );
			if (!is_dir( $dn )) {
				if (mkdir( $dn, 0700, true )) {
					if (file_put_contents( $fn, $text, FILE_APPEND )) {
						return;
					}
				}
			}
			$altfn = "content/emergency_log.txt";
			$text = "$log: $text";
			file_put_contents( $altfn, $text, FILE_APPEND );
		}
	}

	/*
	=====================
	Stamp

	Writes a log line in the following format:

		YYYY-MM-DD HH:MM:SS.mls <text>

	=====================
	*/
	static function Stamp( $log, $text )
	{
		$now = microtime( true );
		$ms = sprintf( "%03d", ($now - floor($now)) * 1000 );
		$ts = date( "Y-m-d H:i:s.$ms ", (int)$now );
		if (is_array( $text )) {
			array_unshift( $text, $ts );
		} else {
			$text = "$ts$text";
		}
		return self::Put( $log, $text );
	}

}
