<?php /* Copyright (C) 2017 David O'Riva. MIT License.
       ********************************************************/
namespace Typeractive;

/*
================================================================================

Blog

Represents a single blog. Use factory methods to instantiate. 

================================================================================
*/

class Blog
{
	public static $prop;

	/*
	=====================
	__construct
	=====================
	*/
	protected function __construct()
	{
	}

	/*
	=====================
	RenderPage

	Outputs an HTML page for the given blog entry	
	=====================
	*/
	public function RenderPage( $id )
	{
		$md = new \Parsedown();

		$text = file_get_contents( "res/b$id.md" );
		$blog = $md->text( $text );

		$text = file_get_contents( "res/qbio.md" );
		$bio = $md->text( $text );

		$out = str_replace( [
				"{{header}}",
				"{{css}}",
				"{{blog}}",
				"{{bio}}",
				"{{toc}}"
			], [
				file_get_contents( "res/header.html" ),
				file_get_contents( "res/blog.css" ),
				$blog,
				$bio,
				file_get_contents( "res/toc.html" )
			], 
			file_get_contents( "res/frame.html" )
		 );
		echo $out;
	}


	/*
	=====================
	Open

	Gets a Blog instance for the given named blog.
	=====================
	*/
	public static function Open( $user )
	{
		return new Blog();
	}

}
