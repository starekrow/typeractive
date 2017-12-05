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
  * TODO: "~~stream~~" - strikethrough
  * TODO: "^^stream^^" - superscript
  * TODO: ",,stream,," - subscript
  * "> " - blockquote
  * indented text - preformat
  * paragraphs - folded
  * < > & - always converted to literals
  * "* " (or "- " or "+ ") - bullet
  * links - "http:" converted to "hxxp "

================================================================================
*/

class Safedown
{
	const B_NONE = 0;
	const B_EMPTY = 1;
	const B_PRE = 2;
	const B_INDENT = 2;
	const B_PARA = 3;
	const B_LIST = 4;
	const B_QUOTE = 5;
	const B_HEADER = 6;
	const B_EOF = 7;

	protected static $re_line;

	protected $filterLinks;
	protected $handleHashTags;		// TODO: "#tag" in text
	protected $handleNameTags;		// TODO: "@tag" in text

	/*
	=====================
	__construct
	=====================
	*/
	public function __construct( $options = null )
	{
		if (!self::$re_line) {
			self::$re_line = '/'
				. '(?:[ \t\f]*)\r?$|('			// 1 - non-empty line
				.   '( ? ? ?(?:[-+*]|[1-9][0-9]*\.))(?:([ \t]).*?\r?$|\r?$)|'
												// 2 bullet 3 spacer
				.   '( ? ? ?>)(?:([ \t]).*?)?\r?$|'
												// 4 blockquote 5 spacer
				.   '\f?(#{1,6})[ \t]+(.+?)[ \t]*#*[ \t]*\r?$|'
												// 6 - header, 7 - text
				.   '(    |\t)(.*?)\r?$|'		// 8 - indent, 9 - text
				.   '(.*?)\r?$'					// 10 - anything else
				. ')'
				. '/Am';
		}
		if (!$options) {
			return;
		}
		foreach ($options as $k => $v) {
			switch ($k) {
			case "filterLinks":
				$this->$k = $v;
				break;
			}
		}
	}

	/*
	=====================
	RenderText

	Render any stream styles in the text.

	This happens as follows:
	  * links are identified and handled (or removed)
	  * ampersands and broken brackets are sanitized
	  * spacing is normalized, text is trimmed
	  * bold, italic and code styles are applied
	=====================
	*/
	protected function RenderText( $text )
	{
		$t = $text;
		if (is_array( $t )) {
			$t = implode( " ", $t );
		}

		$t = preg_replace( "/[ \t]+/", " ", $t );

		$repl = [];
		if (strpos( "\x00", $t ) !== false) {
			$repl[] = "\x00";
			$t = str_replace( "\x00", "\x000;", $t );
		}

		/*
		$t = preg_replace_callback( 
			'/!?\[([^\[\]]+)\]\( *([^ \)]*)(?: +"([^"]*)")?\)/',
			function ($v) use ($repl) {
				$c = count( $repl );
				$img = false;
				if ($v[0][0] == "!") {
					$r = "<img";
					$img = true;
				} else {
					$r = "<a";
				}
				if ($img && $v[1] !== "") {
					$r .= " alt=\"" . htmlspecialchars( $v[1] ) . "\"";
				}
				if (isset( $v[3] ) && $v[3] !== "") {
					$r .= " title=\"" . htmlspecialchars( $v[3] ) . "\"";
				}
				$r .= $img ? " src=\"" : " href=\"";
				if (preg_match( 
					"@^https?://.+|^ftp:.+|^/.*|[^:/]+(?:/.*)$", $v[2] )) {
					$r .= htmlspecialchars( $v[2] );
				} else {
					$r .= "invalid";
				}
				$r .= "\"";
				$r .= ">";
				$repl[] = $r;
				if ($img) {
					return "\x00$c;";
				}
				$d = count( $repl );
				return "\x00$c;" . $v[1] . "\x00$d;";
			},
			$t );

		// TODO: reference links?
		//$re_link2 = '/!?\[([^\]]+)\]\[([^ \t\]]*)\]/';
		*/

		$t = preg_replace_callback( 
			"/&[a-zA-Z][a-zA-Z0-9]*;|&#[0-9]{1,6};|&#x[0-9a-fA-F]{1,5};|&/",
			function ($v) {
				return $v == "&" ? "&amp;" : $v;
			},
			$t );

		$t = str_replace( [ "<", ">" ], [ "&lt;", "&gt;" ], $t );
		$t = preg_replace( "/(^| )\\*\\*([^ ](?:.+[^ ]|))\\*\\*( |$)/", "\\1<b>\\2</b>\\3", $t );
		$t = preg_replace( "/(^| )\\*([^ ](?:.+[^ ]|))\\*( |$)/", "\\1<i>\\2</i>\\3", $t );
		$t = preg_replace( "/(^| )`(.+)`( |$)/", "\\1<code>\\2</code>\\3", $t );
		$t = preg_replace( 
			[ "/http:([^ \t<])/", "/https:([^ \t<])/", "/ftp:([^ \t<])/", "/mailto:([^ \t<])/" ],
			[ "hxxp \\1", "hxxps \\1", "fxp \\1", "mxilto \\1" ],
			$t );
		if (count( $repl )) {
			$t = preg_replace_callback( "/\x00([0-9]+);",
				function( $v ) use ($repl) {
					return $repl[ $v[1] ];
				},
				$t );
		}
		return $t;
	}

	/*
	=====================
	ParseFile
	=====================
	*/
	protected function ParseFile( $text )
	{
		$stack = [ (object)[
			 "type" => self::B_NONE
			,"output" => []
			,"start" => ""
			,"end" => ""
			,"break" => 0
		]];

		$this->replacements = (object)[
			 "count" => 1
			,"0" => "\x00"
		];
		$scan = 0;
		$limit = strlen( $text );
		$deep = 1;
		$sp = null;
		$line = 0;
		$re_line = self::$re_line;
		$nextline = false;
		$next = null;

		for (;;) {
			//echo "At $scan ($deep): >>>";
			//echo str_replace( ["\r","\n","\t"],["\\r","\\n","\\t"], substr( $text, $scan, 10 ) );
			//echo "\n";
			$got = $scan < $limit && preg_match( $re_line, $text, $r, 0, $scan );
			//var_dump( $r );

			/*
			if ($got && (!isset( $r[0] ) || !isset( $r[1] ))) {
				echo "At $scan ($deep): >>>";
				
				echo str_replace( ["\r","\n","\t"],["\\r","\\n","\\t"], substr( $text, $scan, 10 ) );
				var_dump( $r );
			}
			*/

			if (!$got) {
				$next = [ self::B_EOF, "", "" ];
				$deep = 1;
			} else if ($r[0] === "" || !isset( $r[1] ) || $r[1] === "") {
				$ep = $stack[ count( $stack ) - 1 ];
				++$ep->break;
				$nextline = true;
			} else if (isset( $r[10] ) && $r[10] !== "") {
				$ep = $stack[ count( $stack ) - 1 ];
				if ($ep->type == self::B_PARA && !$ep->break) {
					$ep->output[] = " ";
					$ep->output[] = $r[10];
				} else {
					$next = [ self::B_PARA, "<p>", "</p>", $r[10] ];
					/*
					$pp = $stack[ $deep - 1 ];
					if ($pp->type == self::B_LIST && !$pp->pmode) {
						$next = [ self::B_PARA, "", "", $r[9] ];
					}
					*/
				}
				$nextline = true;
			} else if ($r[2]) {
				$listnums = strspn( $r[2], "-+*" ) ? false : true;
				$tt = $listnums ? "ol" : "ul";
				if ($sp && $sp->type == self::B_LIST) {
					//if ($sp->break) {
					//	array_splice( $sp->output, 1, 0, "<p>" );
					//	$sp->output[] = "</p>";
					//}
					$next = [ self::B_LIST, "<li>", $sp->end ];
					$sp->end = "";
				} else {
					$next = [ self::B_LIST, "<$tt><li>", "</$tt>" ];
				}
				if ($r[3] === "") {
					$nextline = true;
				} else {
					$scan += strlen( $r[2] ) + strlen( $r[3] );
				}
			} else if ($r[4]) {
				if ($sp && $sp->type != self::B_QUOTE ) {
					$next = [ self::B_QUOTE, "<blockquote>", "</blockquote>" ];
				} else {
					++$deep;
				}
				if ($r[5] === "") {
					$nextline = true;
				} else {
					$scan += strlen( $r[4] ) + strlen( $r[5] );
				}
			} else if ($r[6]) {
				$lev = strlen( $r[6] );
				$next = [ self::B_HEADER, "<h$lev>", "</h$lev>", 
					$r[7] ];
				$nextline = true;
			} else if ($r[8] !== "") {
				if ($sp && $sp->type == self::B_LIST) {
					++$deep;
					$scan += strlen( $r[8] );
				} else {
					if (!$sp || $sp->type != self::B_PRE) {
						$next = [ self::B_PRE, "<pre><code>", "</code></pre>", $r[9] . "\n" ];
					} else {
						if ($sp->break) {
							$sp->output[] = str_repeat( "\n", $sp->break );
							$sp->break = 0;
						}
						$sp->output[] = $r[9] . "\n";
					}
					$nextline = true;
				}
			}
			if ($next) {
				$out = [];
				$break = 0;
				while (count( $stack ) > $deep) {
					$sp = array_pop( $stack );
					//echo "unstack {$sp->type} @" . (count($stack)) . "\n";
					// collapse
					$pp = $stack[ count( $stack ) - 1 ];
					$pp->output[] = $sp->start;
					$part = implode( "", $sp->output );
					if ($sp->type == self::B_PARA) {
						$part = $this->RenderText( $part );
					}
					$pp->output[] = $part;
					$pp->output[] = $sp->end;
					$break += $sp->break;
				}
				$pp = $stack[ count( $stack ) - 1 ];
				$break += $pp->break;
				$pp->break = 0;
				while ($break > 1) {
					$pp->output[] = "<br>";
					--$break;
				}
				if ($next[0]) {
					if ($next[0] == self::B_EOF) {
						break;
					}
					//echo "stack $next[0]\n";
					$sp = (object)[
						 "type" => $next[0]
						,"output" => []
						,"start" => $next[1]
						,"end" => $next[2]
						,"break" => 0
					];
					if (isset( $next[3] )) {
						$sp->output[] = $next[3];
					}
					$stack[] = $sp;
				}
				$deep = count( $stack );
				//echo "deep now $deep\n";
				$next = null;
			}
			if ($nextline) {
				//echo "To next line\n";
				$scan += strlen( $r[0] ) + 1;
				++$line;
				$deep = 1;
				$nextline = false;
			}
			$sp = ($deep < count( $stack )) ? $stack[ $deep ] : null;
		}
		return implode( "", $stack[0]->output );
	}

	/*
	=====================
	ParseFile2
	=====================
	*/
	protected function ParseFile2( $text )
	{
		$stack = [ (object)[
			 "type" => self::B_NONE
			,"output" => ""
			,"start" => ""
			,"end" => ""
			,"break" => 0
		]];

		$this->replacements = (object)[
			 "count" => 1
			,"0" => "\x00"
		];
		$scan = 0;
		$limit = strlen( $text );
		$deep = 1;
		$sp = null;
		$line = 0;
		$re_line = self::$re_line;
		$nextline = false;
		$next = null;
		$spc = 0;
		$eol = null;
		$textline = false;

		for (;;) {
			//echo "At $scan ($deep): >>>";
			
			//echo str_replace( ["\r","\n","\t"],["\\r","\\n","\\t"], substr( $text, $scan, 10 ) );
			//echo "\n";

			if ($scan >= $limit) {
				$c = "\n";
			} else {
				$c = $text[$scan++];
			}
			if ($c != " ") {
				$spc = 0;
			}
			switch( $c ) {
			case " ":
				++$spc;
			case "\t":
				if ($c != "\t" && $spc < 4) {
					break;
				}
				$spc = 0;
				$eol = strspn( $text, " \t\r", $scan );
				if ($scan + $eol >= $limit || $text[$scan + $eol] == "\n") {
					$ep = $stack[ count( $stack ) - 1 ];
					++$ep->break;
					$scan += $eol + 1;
					$c = "\n";
				} else if ($sp && $sp->type == self::B_LIST) {
					++$deep;
				} else {
					$next = $eol = strpos( $text, "\n", $scan );
					if ($eol === false) {
						$eol = $next = $limit;
					}
					if ($eol && $scan[ $eol - 1 ] == "\r") {
						--$eol;
					}
					$dat = substr( $text,  $scan, $eol - $scan );
					$scan = $next + 1;
					$c = "\n";
					if (!$sp || $sp->type != self::B_PRE) {
						$next = [ self::B_PRE, "<pre><code>", "</code></pre>", $dat . "\n" ];
					} else {
						if ($sp->break) {
							$sp->output .= str_repeat( "\n", $sp->break );
							$sp->break = 0;
						}
						$sp->output .= $dat . "\n";
					}
				}
				break;

			case "*": case "+": case "-":
				$d = $scan >= $limit ? null : $text[$scan];
				if ($d != " " && $d != "\r" && $d != "\n" && $d != "\t") {
					// text?
					$textmode = true;
					break;
				}
				$listnums = false;
				$tt = $listnums ? "ol" : "ul";
				if ($sp && $sp->type == self::B_LIST) {
					//if ($sp->break) {
					//	array_splice( $sp->output, 1, 0, "<p>" );
					//	$sp->output[] = "</p>";
					//}
					$next = [ self::B_LIST, "<li>", $sp->end ];
					$sp->end = "";
				} else {
					$next = [ self::B_LIST, "<$tt><li>", "</$tt>" ];
				}
				++$scan;
				break;

			case "0": case "1": case "2": case "3": case "4":
			case "5": case "6": case "7": case "8": case "9":
				$textline = true;
				break;

			case ">":
				$d = $scan >= $limit ? null : $text[$scan];
				if ($d != " " && $d != "\r" && $d != "\n" && $d != "\t") {
					break;
				}
				if ($sp && $sp->type != self::B_QUOTE ) {
					$next = [ self::B_QUOTE, "<blockquote>", "</blockquote>" ];
				} else {
					++$deep;
				}
				++$scan;
				break;

			case "#":
				// header
				break;

			case "\r":
				if ($scan < $limit) {
					if ($text[$scan] != "\n") {
						break;
					}
					++$scan;
				}
				$c = "\n";
			case "\n":
				// immediate EOL
				if ($scan >= $limit) {
					$next = [ self::B_EOF, "", "" ];
					$deep = 1;
				}
				break;
			default:
				$textline = true;
			}
			if ($textline) {
				$next = $eol = strpos( $text, "\n", $scan );
				if ($eol === false) {
					$eol = $next = $limit;
				}
				if ($eol && $scan[ $eol - 1 ] == "\r") {
					--$eol;
				}
				$dat = substr( $text,  $scan - 1, $eol - $scan + 1 );
				$scan = $next + 1;
				$c = "\n";
				$ep = $stack[ count( $stack ) - 1 ];
				if ($ep->type == self::B_PARA && !$ep->break) {
					$ep->output .= " " . $dat;
				} else {
					$next = [ self::B_PARA, "<p>", "</p>", $dat ];
				}
				$textline = false;
			}
			if ($next) {
				$out = [];
				$break = 0;
				while (count( $stack ) > $deep) {
					$sp = array_pop( $stack );
					//echo "unstack {$sp->type} @" . (count($stack)) . "\n";
					// collapse
					$pp = $stack[ count( $stack ) - 1 ];
					$pp->output .= $sp->start;
					//$pp->output = array_merge( $pp->output, $sp->output );
					//$part = implode( "", $sp->output );
					$part = $sp->output;
					if ($sp->type == self::B_PARA) {
						$part = $this->RenderText( $part );
					}
					$pp->output .= $part;
					$pp->output .= $sp->end;
					$break += $sp->break;
				}
				$pp = $stack[ count( $stack ) - 1 ];
				$break += $pp->break;
				$pp->break = 0;
				while ($break > 1) {
					$pp->output .= "<br>";
					--$break;
				}
				if ($next[0]) {
					if ($next[0] == self::B_EOF) {
						break;
					}
					//echo "stack $next[0]\n";
					$sp = (object)[
						 "type" => $next[0]
						,"output" => ""
						,"start" => $next[1]
						,"end" => $next[2]
						,"break" => 0
					];
					if (isset( $next[3] )) {
						$sp->output .= $next[3];
					}
					$stack[] = $sp;
				}
				$deep = count( $stack );
				//echo "deep now $deep\n";
				$next = null;
			}

			if ($c == "\n") {
				//++$this->line;
				$deep = 1;
			}
			$sp = ($deep < count( $stack )) ? $stack[ $deep ] : null;
		}
		return $stack[0]->output;
	}

	protected static $re_bold = 
		"/[*][*]((?:\\\\[*]|[^*]|[*][^*][*])+?)[*][*](?![*])/A";
	protected static $re_em = 
		"/[*]((?:\\\\[*]|[^*]|[*][*][^*][*][*])+?)[*](?![*])/A";
	protected static $re_entity = 
		"/&(?:[a-zA-Z][a-zA-Z0-9]+|#[0-9]+|#[xX][0-9a-fA-F]+);/A";

	/*
	=====================
	HandleInlines
	=====================
	*/
	protected function HandleInlines( $text )
	{
		$res = "";
		$scan = 0;
		$last = 0;
		$limit = strlen( $text );
		for (;;) {
			$scan += strcspn( $text, "\\*_[<>&", $scan );
			if ($scan >= $limit) {
				break;
			}
			//echo "text @ $scan\n";
			$at = $scan;
			$put = null;
			switch ($text[$scan++]) {
			case "\\":
				if ($scan < $limit) {
					switch ($text[$scan]) {
					case "\\":
					case "*":
					case "[":
						$put = $text[$scan++];
					}
				}
				break;

			case "*":
				if ($scan < $limit && $text[$scan] == "*"
					&& preg_match( self::$re_bold, $text, $r, 0, $scan - 1 )) {
					$scan += strlen( $r[0] ) - 1;
					$t = $this->HandleInlines( $r[1] );
					$put .= "<strong>$t</strong>";
				} else if (
					preg_match( self::$re_em, $text, $r, 0, $scan - 1 )) {
					$scan += strlen( $r[0] ) - 1;
					$t = $this->HandleInlines( $r[1] );
					$put .= "<em>$t</em>";
				}
				break;

			case "<":
				$put = "&lt;";
				break;

			case ">":
				$put = "&gt;";
				break;

			case "&":
				if (!preg_match( self::$re_entity, $text, $r, 0, $scan - 1 )) {
					$put = "&amp;";
				}
				break;

			case "[":
				// TODO: link
			}
			if ($put !== null) {
				if ($at != $last) {
					$res .= substr( $text, $last, $at - $last );
				}
				$res .= $put;
				$last = $scan;
			}
		}
		if (!$last) {
			return $text;
		}
		if ($last) {
			if ($scan > $last) {
				$res .= substr( $text, $last );
			}
			return $res;
		}
		return $text;
	}

	/*
	=====================
	ClassifyLine
	=====================
	*/
	protected function ClassifyLine( $text, &$pos )
	{
		//$scan = $off;
		//var_dump( $pos );
		$scan = $pos[0];
		$indent = 0;
		for (;;) {		
			if (!isset( $text[$scan] )) {
				return self::B_EMPTY;
			}
			$c = $text[$scan++];
			if ($c == "\t") {
				$pos[0] = $scan;
				return self::B_INDENT;
			}
			if ($c == ' ') {
				if (++$indent == 4) {
					$pos[0] = $scan;
					return self::B_INDENT;
				}
				continue;
			}
			if ($c == ">") {
				if (isset( $text[ $scan ] ) && (
					$text[$scan] == " " || $text[$scan] == "\t")) {
					++$scan;
				}
				$pos[0] = $scan;
				return self::B_QUOTE;
			}
			if ($c == "*" || $c == "-" || $c == "+") {
				if (isset( $text[ $scan ] ) && (
					$text[$scan] == " " || $text[$scan] == "\t")) {
					++$scan;
					$pos[0] = $scan;
					return self::B_LIST;
				}
			}
			if ($c == "\r") {
				if (isset( $text[ $scan ] ) && $text[$scan] == "\n") {
					++$scan;
				}
				return self::B_EMPTY;
			}
			if ($c == "\n") {
				return self::B_EMPTY;
			}
			--$scan;
			return self::B_PARA;
		}
	}

	/*
	=====================
	HandleBlock
	=====================
	*/
	protected function HandleBlock( $lines, $file )
	{
		$block = null;
		$out = "";
		$text = "";
		$break = 0;
		$start = 0;
		for ($i = 0, $ic = count( $lines ); $i <= $ic; ++$i) {
			if ($i == $ic) {
				$p = self::B_EOF;
			} else {
				$p = $this->ClassifyLine( $file, $lines[$i] );
			}
			if ($p == self::B_EMPTY) {
				++$break;
				continue;
			}
			// Continue the current block
			switch ($block) {
			case self::B_PRE:
				if ($p == self::B_INDENT) {
					$l = $lines[ $i ];
					if ($break) {
						$text .= str_repeat( "\n", $break );
						$break = 0;
					}
					$text .= substr( $file, $l[0], $l[1] - $l[0] ) . "\n";
					continue 2;
				}
				break;

			case self::B_PARA:
				if ($p == self::B_PARA && !$break) {
					$l = $lines[ $i ];
					$text .= " " . substr( $file, $l[0], $l[1] - $l[0] );
					continue 2;
				}
				break;
			case self::B_QUOTE:
				if ($p == self::B_QUOTE || ($p == self::B_PARA && !$break)) {
					continue 2;
				}
				break;
			case self::B_LIST:
				if ($p == self::B_PARA && !$break) {
					continue 2;
				}
				if ($p == self::B_LIST) {
					$out .= "<li>";
					$out .= $this->HandleBlock( 
						array_slice( $lines, $start, $i - $start ),
						$file 
					);
					$start = $i;
					continue 2;
				}
				break;
			}

			// Previous block ended with this line
			switch ($block) {
			case self::B_PRE:
				$text = htmlspecialchars( $text );
				$out .= "<code><pre>" . $text . "</pre></code>";
				break;
			case self::B_PARA:
				$text = $this->HandleInlines( trim( $text, " \t" ) );
				//$text = trim( $text, " \t" );
				$out .= "<p>$text</p>";
				break;
			case self::B_LIST:
				$out .= "<li>";
				$out .= $this->HandleBlock( 
					array_slice( $lines, $start, $i - $start - $break ), 
					$file 
				);
				$out .= "</ul>";
				break;
			case self::B_QUOTE:
				$out .= "<blockquote>";
				$out .= $this->HandleBlock( 
					array_slice( $lines, $start, $i - $start - $break ), 
					$file 
				);
				$out .= "</blockquote>";
				break;
			case self::B_PRE:
				$out .= "</code></pre>";
				break;
			}

			// Fill in any white space
			if ($break > 1) {
				$out .= str_repeat( "<br>", $break - 1 );
			}
			$break = 0;

			// Start a new block
			switch ($p) {
			case self::B_LIST:
				$start = $i;
				$out .= "<ul>";
				$block = $p;
				break;

			case self::B_QUOTE:
				$start = $i;
				$block = $p;
				break;

			case self::B_PARA:
				$l = $lines[$i];
				$text = substr( $file, $l[0], $l[1] - $l[0] );
				$block = $p;
				break;

			case self::B_INDENT:
				$l = $lines[$i];
				$text = substr( $file, $l[0], $l[1] - $l[0] ) . "\n";
				$block = self::B_PRE;
				break;

			case self::B_EOF:
				break 2;

			default:
				$block = null;
			}
		}
		return $out;
	}

	/*
	=====================
	ParseFile3
	=====================
	*/
	protected function ParseFile3( $text )
	{
		$scan = 0;
		$lines = [];
		$ll = strlen( $text );
		// TODO: This could be written to parse blocks from a stream instead of
		// loading it all at once.
		for (;;) {
			$ln = strpos( $text, "\n", $scan );
			if ($ln === FALSE) {
				$lines[] = [ $scan, strlen( $text ) ];
				break;
			}
			$lines[] = [ $scan, $ln ];
			$scan = $ln + 1;
			if ($scan >= $ll) {
				break;
			}
		}
		return $this->HandleBlock( $lines, $text );
	}

	/*
	=====================
	text
	=====================
	*/
	public function text( $text )
	{
		return $this->ParseFile3( $text );
	}
}


/* */

require_once( "../lib/Parsedown.php" );

function tsd()
{
	$text = <<<END
this is a
*paragraph* of text
with three lines & entities: &copy; &amp; 

    preformatted text
    Goes *here*

    *with* <stuff>

Another paragraph


Paragraph after break
with two lines

* list entry 1
* list entry 2

> This is a quoted
> paragraph with
>
>  * a list
>  * with entries
>
> > A quoted paragraph within the
> > quoted section
>
> Final quoted paragraph

Final paragraph @ http://foo/bar
END;
	if (1) {
		$sd = new Safedown();
		$t = $sd->text( $text );
		echo $t;
		echo "\n\n";
		die;
	}

	$text = file_get_contents( "../res/b5.md" );

	$timer = microtime(true);
	$sd = new Safedown();
	for ($i = 0; $i < 100; $i++) {
		$t = $sd->text( $text );
	}
	$tsafe = microtime(true) - $timer;

	$timer = microtime(true);
	$md = new \Parsedown();
	for ($i = 0; $i < 100; $i++) {
		$t = $md->text( $text );
	}
	$tmd = microtime(true) - $timer;

	echo "safedown: $tsafe, parsedown: $tmd\n";
	die;


	echo $t;
	echo "\n\n";

	$sd = new \Parsedown();
	$t = $sd->text( $text );
	echo $t;
	echo "\n\n";

}
tsd();

/* */
