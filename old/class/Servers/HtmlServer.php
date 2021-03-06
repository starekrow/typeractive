<?php /* Copyright (C) 2017 David O'Riva. MIT License.
       ********************************************************/
namespace Typeractive;

/*
================================================================================

HtmlServer

Serves complete pages or page body updates. This class allows subclassed 
servers to concentrate on their own output without worrying about the exact
state of the site frame.

================================================================================
*/

class HtmlServer extends Server
{
	protected $defaultExtensions = [
		 "dialog"
		//,"form"
	];

	protected $html;
	protected $css;
	protected $script;
	protected $tokens;
	protected $title;
	protected $location;
	protected $headblock;

	/*
	=====================
	ReplaceAngTokens

	Replaces tokens in the form "<<token>>" with their corresponding values.
	Token characters are limited to [-_a-zA-Z0-9]. If a token ends with "?",
	the token string will be removed if no matching tokens are found.

	=====================
	*/
	public function ReplaceAngTokens( $string, $toks )
	{
		$string = preg_replace_callback( 
			"/<<foreach:\\s*([^>]+?)\\s*>>([\\s\\S]*?)<<\\/foreach\\s*>>/", 
			function ($got) use ($toks) {
				//error_log( "got here " . var_export( $got ) );
				if (empty( $toks[ $got[1] ] )) {
					return "";
				}
				$l = $toks[ $got[1] ];
				if (!is_array( $l )) {
					return "";
				}
				$out = [];
				foreach ($l as $el) {
					$out[] = $this->ReplaceAngTokens( $got[2], $el );
				}
				return implode( "", $out );
			},
			$string
		);
		$string = preg_replace_callback( 
			"/<<ifempty:\\s*([^>]+?)\\s*>>([\\s\\S]*?)<<\\/if\\s*>>/", 
			function ($got) use ($toks) {
				//error_log( "got here " . var_export( $got ) );
				if (empty( $toks[ $got[1] ] )) {
					return $got[2];
				}
				$l = $toks[ $got[1] ];
				if (is_array( $l ) && !count( $l )) {
					return $got[2];
				}
				return "";
			},
			$string
		);
		$string = preg_replace_callback( "/<<[-_a-zA-Z][-_a-zA-Z0-9]+\\??>>/", 
			function ($got) use ($toks) {
				$got = $got[0];
				$gl = strlen( $got );
				if ($got[ $gl - 3 ] == '?') {
					$got = substr( $got, 2, $gl - 5 );
					$def = "";
				} else {
					$got = substr( $got, 2, $gl - 4 );
					$def = $got;
				}
				return isset( $toks[$got] ) ? $toks[ $got ] : $def;
			},
			$string
		);
		return $string;
	}

	/*
	=====================
	ReadFileWithTokens
	=====================
	*/
	public function ReadFileWithTokens( $filename, $toks = null )
	{
		if (!$toks) {
			$toks = $this->tokens;
		}
		$got = file_get_contents( $filename );
		$got = $this->ReplaceAngTokens( $got, $toks );
		echo $got;
	}

	/*
	=====================
	EmitFullFrame

	Renders a "standard" site page with the given HTML.
	The page includes basic support script and css, a loader mask, and a site
	header with menu/identity block.
	=====================
	*/
	public function EmitFullFrame()
	{
		echo "<!DOCTYPE html>";
		echo "<html><head>";

		$add_vpmeta = true;
		$add_title = true;
		if ($this->headblock) {
			$hb = $this->ReplaceAngTokens( $this->headblock, $this->tokens );
			if (strpos( $hb, "<meta name=\"viewport\"" ) !== false 
				|| strpos( $hb, "<meta name=\'viewport\'" ) !== false 
				|| strpos( $hb, "<meta name=viewport " ) !== false 
			   ) {
				$add_vpmeta = false;
			}
			if (strpos( $hb, "<title>" ) !== false) {
				$add_title = false;
			}
			echo $hb;
		}

		if ($add_vpmeta) {
			echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
		}

		if ($add_title) {
			echo "<title>";
			if (isset( $this->title )) {
				echo htmlspecialchars( $this->title );
			}
			echo "</title>";
		}

		echo "<base href=\"" . Http::$appRootUrl . "/\">";
		
		echo "<style type=\"text/css\">";
		$this->ReadFileWithTokens( "res/site_frame.css" );
		echo "</style>";

		echo "<style id=pagecss type=\"text/css\">";
		if ($this->css) {
			echo $this->ReplaceAngTokens( $this->css, $this->tokens );
		}
		echo "</style>";

		echo "<script type=\"text/javascript\">";
		$this->ReadFileWithTokens( "res/main.js" );
		echo "</script>";

		if ($this->script) {
			echo "<script type=\"text/javascript\">";
			echo $this->ReplaceAngTokens( $this->script, $this->tokens );
			echo "</script>";
		}

		echo "</head><body>";

		$this->ReadFileWithTokens( "res/site_header.html" );
		$this->ReadFileWithTokens( "res/ext_dialog.html" );

		echo "<div id=loadermask class=loadermask><div class=mask></div>";
		echo "<table><tr><td>Loading...</td></tr></table></div>";

		echo "<div id=pagebody><div>";

		echo $this->GetHtml();

		echo "</div></div>";

		//readfile( "res/site_footer.html" );

		echo "</body></html>";

		$this->didReply = true;
	}

	/*
	=====================
	GetHtml
	=====================
	*/
	function GetHtml()
	{
		$html = $this->html;
		if (is_array( $html )) {
			$html = implode( "", $html );
		}
		$html = $this->ReplaceAngTokens( $html, $this->tokens );
		return $html;		
	}

	/*
	=====================
	SetupSessionTokens
	=====================
	*/
	function SetupSessionTokens()
	{
		if (session_id() === "") {
			session_start();
		}
		$login = !empty( $_SESSION['userid'] );
		$this->tokens->hide_for_user = $login ? "display:none;" : "";
		$this->tokens->hide_for_guest = $login ? "" : "display:none;";
		$this->tokens->username = $login ? $_SESSION['username'] : "";
	}

	/*
	=====================
	RequestHandler
	=====================
	*/
	function RequestHandler()
	{
		$this->html = [];
		$this->tokens = new Dict();

		$this->tokens->approot = Http::$appRootUrl;
		$this->tokens->backlink = Http::$referrer;
		$this->tokens->defaultfg = "#444444";
		$this->tokens->defaultbg = "#f3efe9";
		$this->tokens->buttonfg = $this->tokens->defaultfg;
		$this->tokens->buttonbg = "#d4c9b9";
		$this->tokens->monofonts = "hack,Consolas,monaco,monospace";
		$this->SetupSessionTokens();

		$this->tokens->sitename = "MetalCoder";
		$this->tokens->sitename_tld = ".com";
		$this->tokens->sitemenu = [
			 [ "link" => "starekrow", "label" => "Blog" ]
			,[ "link" => "projects", "label" => "Projects" ]
			,[ "link" => "about", "label" => "About" ]
		];

		$this->title = "MetalCoder";

		$this->GetPage();

		if ($this->didReply) {
			return;
		}

		if ($this->args->_autoloader_) {
			$html = $this->GetHtml();
			$this->ReplyJson( [
				"html" => [
					"pagebody" => $html
				]
				,"loc" => [

				]
			]);
		} else {
			$this->EmitFullFrame();
		}
	}

}
