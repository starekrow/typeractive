<?php /* Copyright (C) 2017 David O'Riva. MIT License.
       ********************************************************/
namespace Typeractive;

/*
================================================================================

PageServer

Serves out a more or less static page from a page record.

================================================================================
*/

class PageServer extends HtmlServer
{
	/*
	=====================
	RenderPage
	=====================
	*/
	function RenderPage( $page, $options = null )
	{
		$options = new Dict( $options );
		$res = (object)[
			 "html" => null
			,"raw" => null
			,"headblock" => null
			,"tokens" => (object)[]
		];
		$txt = $page->GetBody();
		$md = new \Parsedown();
		$txt = preg_replace_callback( "/<markdown>([\\s\\S]*?)<\\/markdown>/",
			function ($got) use ($md) {
				return $md->text( $got[1] );
			}, $txt );
		if (preg_match( "/^\\s*<!DOCTYPE/i", $txt )) {
			$res->raw = $txt;
		} else if (preg_match( 
			"/^[^\\S]*<head>(.*?)<\\/head>(?:[^\\S]*<body>)?(.*)(?:<\\/body>[^\\S]*)?$/", 
				$txt, $got )
		   ) {
			$res->headblock = $got[1];
			$res->html = $got[2];
		} else {
			$res->html = $txt;
		}
		return $res;
	}


	/*
	=====================
	GetPage
	=====================
	*/
	function GetPage()
	{
		$id = $this->request->id;


		$id = $this->request->id;

		$key = "full_page_" . $id;
		if (!Cache::lock( $key, 0, $val, 30, 10 )) {
			$val = $val ? $val : Cache::wait( $key, 10 );
			if (!$val) {
				$this->html = "Error retrieving page. Please try again.";
				return;
			}
		} else {
			$page = PageData::Load( $id );
			$val = self::RenderPage( $page );
			Cache::set( $key, $val, 300 );		// 5 minutes
			Cache::unlock( $key );
		}
		$got = new Dict( $val );
		if (isset( $got->raw )) {
			$this->tokens->Merge( $got->tokens );
			$t = $this->ReplaceAngTokens( $got->raw, $this->tokens );
			$this->ReplyRaw( $t );
		} else {
			$this->html = $got->html;
			$this->tokens->Merge( $got->tokens );
			$this->headblock = $got->headblock;			
		}
	}
}
