<?php /* Copyright (C) 2017 David O'Riva. All rights reserved.
       ********************************************************/
namespace Typeractive;

/*
================================================================================

safe - Converts text to "safe" HTML with some styling

This applies a restricted subset of markdown, used for such things as user
comments.

The supported markdown is:

  * "*stream*", "**stream**" - italic, bold
  * "_stream_", "__stream__" - italic, bold
  * "~~stream~~" - strikethrough
  * "> " - blockquote
  * indented text - preformat
  * paragraphs - folded
  * links - "http:" converted to "hxxp "
  * images - folded shut, max width/height of 300px
    * require JS to activate, links not in document
  * < > & - always converted to literals
  * "* " - bullet

================================================================================
*/

class Safedown
{
	/*
	=====================
	ParaRender
	=====================
	*/
	public static function ParaRender( $lines )
	{
		$t = implode( " ", $lines );
		$t = preg_replace( "/[ \t]+/", " ", $t );
		$t = preg_replace( "/(^| )**([^ ](?:.+[^ ]|))**( |$)", "\1<b>\2</b>\3" );
		$t = preg_replace( "/(^| )*([^ ](?:.+[^ ]|))*( |$)", "\1<i>\2</i>\3" );
		$t = preg_replace( "/(^| )`(.+)`( |$)", "\1<code>\2</code>\3" );
		return $t;
	}

	/*
	=====================
	ExpandTabs
	=====================
	*/
	public static function ExpandTabs( $line )
	{
		$lp = explode( "\t", $line );
		if (count( $lp ) == 1) {
			return $lp[0];
		}
		$s = [ " ", "  ", "   ", "    " ];
		$off = 0;
		$out = [];
		for ($i = 0, $ic = count( $lp ) - 1; $i < $ic; ++$i) {
			$out[] = $lp[ $i ];
			$off += strlen( $lp[ $i ] );
			$out[] = $s[ 3 - ($off & 3) ];
		}
		$out[] = $lp[ $i ];
		return implode( "", $out );
	}

	/*
	=====================
	BlockRender
	=====================
	*/
	public static function BlockRender( $lines )
	{
		$pre = 0;
		$quot = 0;
		$para = 0;

		$out = [];

		for ($i = 0; $i <= count( $lines ); ++$i) {
			$end = false;
			$l = "";
			if ($i == count( $lines )) {
				$end = true;
			} else {
				$l = $lines[ $i ];
			}
			if (trim( $l ) === "") {
				$l = "";
			}
			if (preg_check( "/^ ? ? ?&lt;(?: (.*))?$/", $l, $got )) {
				++$quot;
				$lines[ $i ] = $got[1];
				continue;
			} else {
				if ($quot) {
					$out[] = "<blockquote>";
					$b2 = array_slice( $lines, $i - $quot - 1, $quot );
					$out[] = self::BlockRender( $b2 );
					$out[] = "</blockquote>";
					$quot = 0;
					continue;
				}
			}
			if ($l == "") {
				if ($para) {
					$out[] = "<p>";
					$b2 = array_slice( $lines, $i - $para - 1, $para );
					$out[] = self::ParaRender( $b2 );
					$out[] = "</p>";
					$para = 0;
					continue;
				} else {
					$out[] = "<br>";
				}
			}
		}
		return implode( "", $out );
	}

	/*
	=====================
	Run
	=====================
	*/
	public static function Run( $args, $path, $request )
	{
		$t = $args->text;

		$t = str_replace( [
				 "&"
				,"<"
				,">"
			], [
				 "&amp;"
				,"&lt;"
				,"&gt;"
			], $t );
		$t = explode( "\n", $t );
		$t = preg_replace( "/(^|[^_a-zA-Z0-9])http:([\\S])/", '$1hxxp_$2', $t );
		$t = preg_replace( "/(^|[^_a-zA-Z0-9])https:([\\S])/", '$1hxxps_$2', $t );
		$t = preg_replace( "/(^|[^_a-zA-Z0-9])ftp:([\\S])/", '$1fxp_$2', $t );
		$t = preg_replace( "/(^|[^_a-zA-Z0-9])mailto:([\\S])/", '$1mxilto_$2', $t );

		$fold = 0;
		$pre = 0;
		$ql = 0;
		for ($i = 0; $i < count( $t ); ++$i) {
			$t[$i] = self::ExpandTabs( $t[$i] );
		}
		return self::BlockRender( $t );
	}
}
