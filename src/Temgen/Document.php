<?php
namespace Temgen;

class Document
{
	protected $contents = '';
	
	protected $filename = null;
	
	public function __construct($filename = null) {
		if ($filename) {
			$this->load($filename);
		}
	}
	
	public function load($filename) {
		$this->filename = $filename;
		$this->setContents(file_get_contents($filename));
	}
	
	public function save($filename) {
		return file_put_contents($filename, $this->getContents());
	}
	
	public function getExtension() {
		return $this->filename ? pathinfo($this->filename, PATHINFO_EXTENSION) : 'txt';
	}
	
	public function setFilename($name) {
		$this->filename = $name;
	}
	
	public function getFilename() {
		return $this->filename;
	}
	
	public function setContents($content) {
		$this->contents = $content;
	}
	
	public function getContents() {
		return $this->contents;
	}
}