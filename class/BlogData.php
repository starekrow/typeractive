<?php /* Copyright (C) 2017 David O'Riva. MIT License.
       ********************************************************/
namespace Typeractive;

/*
================================================================================

BlogData

Represents a single blog. Use factory methods to instantiate. 

================================================================================
*/

class BlogData
{
	public $id;
	protected $record;
	protected $text;


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
		$this->text = (object)[];
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
	GetTextField
	=====================
	*/
	protected function GetTextField( $field )
	{
		if (!empty( $this->text->$field )) {
			return $this->text->$field->GetText();
		}
		if (!$this->record->$field) {
			return "";
		}
		// TODO: safety checks
		$t = TextData::Load( $this->record->$field );
		$this->text->$field = $t;
		return $t->GetText();
	}

	/*
	=====================
	SetTextField
	=====================
	*/
	protected function SetTextField( $field, $text )
	{
		// TODO: safety checks?
		if ($text == null) {
			$text = "";
		}
		if (!$this->record->$field) {
			$t = TextData::Create( 
				$text, 
				"v1:$field:markdown", 
				$this->record->authorid 
			);
			$this->text->$field = $t;
			$this->record->$field = $t->id;
		} else {
			// TODO: history
			if (empty( $this->text->$field )) {
				$this->GetTextField( $field );
			}
			$this->text->$field->SetText( $text );
			$this->text->$field->Save();
		}
	}


	/*
	=====================
	SetBiography
	=====================
	*/
	public function SetBiography( $text )
	{
		// TODO: safety checks?
		$this->SetTextField( "biotext", $text );
	}

	/*
	=====================
	SetTitle
	=====================
	*/
	public function SetTitle( $text )
	{
		// TODO: safety checks?
		$this->SetTextField( "titletext", $text );
	}

	/*
	=====================
	SetHeader
	=====================
	*/
	public function SetHeader( $text )
	{
		// TODO: safety checks?
		$this->SetTextField( "headertext", $text );
	}

	/*
	=====================
	GetAuthor
	=====================
	*/
	public function GetAuthor()
	{
		return $this->record->authorid;
	}

	/*
	=====================
	GetDefaultPost
	=====================
	*/
	public function GetDefaultPost()
	{
		$rp = $this->record->rootpost;
		if (!$rp) {
			$rp = "*latest";
		} else {
			$rp = (string) $rp;
		}
		return $rp;
	}

	/*
	=====================
	GetBiography
	=====================
	*/
	public function GetBiography()
	{
		return $this->GetTextField( "biotext" );
	}

	/*
	=====================
	GetTitle
	=====================
	*/
	public function GetTitle()
	{
		return $this->GetTextField( "titletext" );
	}

	/*
	=====================
	GetHeader
	=====================
	*/
	public function GetHeader()
	{
		return $this->GetTextField( "headertext" );
	}


	/*
	=====================
	SetDefaultPost
	=====================
	*/
	public function SetDefaultPost( $post )
	{
		if ($post === "latest" || $post == "*latest") {
			$post = null;
		}
		$this->record->rootpost = $post;
	}

	/*
	=====================
	Save
	=====================
	*/
	public function Save()
	{
		$this->record->Flush();
	}

	/*
	=====================
	ListPosts

	Returns an array of posts (might be empty).
	=====================
	*/
	public function ListPosts( $type = null )
	{
		return PostData::ListBlogPosts( $this->id, $type );
	}

	/*
	=====================
	CreateDraft

	Returns a PostData for the draft.
	=====================
	*/
	public function CreateDraft( $info )
	{
		$f = new Dict( $info );
		//if ($f->text == "" && $f->title == "") {
		//	throw new \Exception( "Refusing to create empty post" );
		//}
		$p = PostData::Create( $this->id, $this->record->authorid );
		$p->SetDraft( $f->text );
		$p->SetTitle( $f->title );
		return $p;
	}

	/*
	=====================
	Find

	Searches blog records by field values. 
	Returns `false` or an array of BlogData.
	=====================
	*/
	public static function Find( $fields )
	{
		$rec = new SqlShadow( "blogs" );
		$got = $rec->Find( $fields );
		if (!$got) {
			return false;
		}
		foreach ($got as &$val) {
			$val = new BlogData( $val );
		}
		return $got;
	}

	/*
	=====================
	Create

	Creates a new blog.
	Returns an instantiated Blog instance.
	=====================
	*/
	public static function Create( $authorid = null )
	{
		$rec = new SqlShadow( "blogs" );
		$rec->authorid = $authorid;
		if (!$rec->Flush()) {
			throw new \Exception( "unable to create blog" );
		}
		return new BlogData( $rec );
	}

	/*
	=====================
	Load

	Gets a BlogData instance for the given id.
	=====================
	*/
	public static function Load( $blogid )
	{
		$rec = new SqlShadow( "blogs", [ "blogid" => $blogid ] );
		if (!$rec->Load()) {
			throw new \Exception( "unable to load blog $blogid" );
		}
		return new BlogData( $rec );
	}
}
