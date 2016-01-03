<?php
namespace Temgen\Document;

class Docx extends \Temgen\Document
{
	///@var bool $autodelete deletes source file when destroying
	protected $autodelete = false;
	
	///@var string $path path to this document
	protected $path;
	///@var \DOMDocument $document document
	protected $document;

	/**
	 * Creates new docx document
	 *
	 * @param string $filename OPTIONAL docx file path
	 */
	public function __construct($path = null) {
		if ($path) {
			$this->load($path);
		}
	}
	
	/**
	 * Removes source file if required
	 */
	public function __destruct() {
		if ($this->autodelete)
			unlink($this->path);
	}
	
	/**
	 * Sets this document to remove source file when destroyed
	 *
	 * @param bool $toggle should source file be removed
	 */
	public function setAutodelete($toggle = true) {
		$this->autodelete = $toggle;
	}

	/**
	 * Loads base document xml, which is used for storing body
	 */
	public function load($path) {
		$this->path = $path;
		
		if (!file_exists($this->path)) {
			throw new \Exception("File '$this->path' doesn't exists.");
		}
		
		$zip = new \ZipArchive();

		if ($zip->open($this->path) !== true) {
			throw new \Exception("File '$this->path' is corrupt or not a docx file.");
		}
		
		$this->setContents($zip->getFromName('word/document.xml'));
		
		if ($this->getContents() === false) {
			throw new \Exception("Failed to open '$this->filename/word/document.xml'.");
		}
		
		$this->document = new \DOMDocument;
		$this->document->loadXML($this->getContents());
	}
	

	/**
	 * Returns parsed document as DOMDocument
	 *
	 * @return DOMDocument document
	 */
	public function getDocument() {
		return $this->document;
	}
	
	/**
	 * Sets documents DOMDocument and updates body
	 *
	 * @param DOMDocument $document
	 */
	public function setDocument(\DOMDocument $document) {
		$this->document = $document;
		$this->setContents($document->saveXML());
	}
	
	/**
	 * Saves document
	 *
	 * @param string $path OPTIONAL path where to save document, this document path by default
	 */
	public function save($path = null) {
		if ($path == null)
			$path = $this->path;
		
		if ($this->path != $path)
			if (!copy($this->path, $path))
				throw new \Exception("Failed to copy '$this->path' to '$path'");
		
		$zip = new \ZipArchive();

		if (!$zip->open($path)) {
			throw new \Exception("File '$this->path' is corrupt or not a package.");
		}
		
		$zip->deleteName('word/document.xml');
		$zip->addFromString('word/document.xml', $this->getContents());
		
		return $zip->close();
	}
	
	public function getExtension() {
		return 'docx';
	}
}