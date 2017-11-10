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
	public $id;
	protected $record;

	public static $tableDef = [
		"autoindex" => "blogid"
	];

	/*
	=====================
	__construct
	=====================
	*/
	protected function __construct( $record )
	{
		$this->record = $record;
		$this->id = $record->blogid;
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
	SetAuthor
	=====================
	*/
	public function SetAuthor( $authorid )
	{
		$this->record->authorid = $authorid;
	}

	/*
	=====================
	SetBiography
	=====================
	*/
	public function SetBiography( $text )
	{
		// TODO: safety checks
		$t = new TextRecord( $this->record->biotext );
		$this->record->biotext = $t->textid;
		$t->SetText( $text );
	}

	/*
	=====================
	SetTitle
	=====================
	*/
	public function SetTitle( $title )
	{
		$t = new TextRecord( $this->record->titletext );
		$this->record->titletext = $t->textid;
		$t->SetText( $text );
	}

	/*
	=====================
	SetHeader
	=====================
	*/
	public function SetHeader( $text )
	{
		// TODO: safety checks
		$t = new TextRecord( $this->record->headertext );
		$this->record->headertext = $t->textid;
		$t->SetText( $text );
	}

	/*
	=====================
	SetDefaultPost
	=====================
	*/
	public function SetDefaultPost( $post )
	{
		if ($post === "latest") {
			$post = null;
		}
		$this->record->rootpost = $post;
	}

	/*
	=====================
	Create

	Creates a new blog.
	Returns an instantiated Blog instance.
	=====================
	*/
	public static function Create()
	{
		$rec = new SqlShadow( "blogs" );
		if (!$rec->Flush()) {
			throw new Exception( "unable to create blog" );
		}
		return new Blog( $rec );
	}

	/*
	=====================
	Open

	Gets a Blog instance for the given id.
	=====================
	*/
	public static function Open( $blogid )
	{
		$rec = new SqlShadow( "blogs", [ "blogid" => $blogid ] );
		if (!$rec->Load()) {
			throw new \Exception( "unable to load blog $blogid" );
		}
		return new Blog( $rec );
	}
}
