<?php
namespace Temgen;

class Document
{
	protected $contents = '';
	
	public function __construct($filename = null) {
		if ($filename) {
			$this->load($filename);
		}
	}
	
	public function load($filename) {
		$this->setContents(file_get_contents($filename));
	}
	
	public function save($filename) {
		return file_put_contents($filename, $this->getContents());
	}
	
	public function getExtension() {
		return 'txt';
	}
	
	public function setContents($content) {
		$this->contents = $content;
	}
	
	public function getContents() {
		return $this->contents;
	}
}