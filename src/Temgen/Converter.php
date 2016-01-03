<?php
namespace Temgen;

interface Converter
{	
	/**
	 * Converts document to different format.
	 *
	 * @param \Temgen\Document $document document to be converted
	 * @param string         $filename target filename, if not set, contents of converted file will be returned
	 * @return bool|string either success of writing file or file contents
	 */
	public function save(Document $document, $filename = null);
	
	/**
	 * Returns expected result extension
	 *
	 * @return string extension
	 */
	public function getExtension();
}