<?php
namespace Temgen\Converter;

/**
 * This convertor converts MD files to HTML
 */
class MD implements \Temgen\Converter
{
	public function save(\Temgen\Document $document, $filename = null) {
		$parser = new \cebe\markdown\Markdown();
		$result = $parser->parse($document->getContents());
		if ($filename)
			return file_put_contents($filename, $result);
		return $result;
	}
	
	public function getExtension() {
		return 'html';
	}
}