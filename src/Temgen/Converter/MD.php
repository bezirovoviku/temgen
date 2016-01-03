<?php
namespace Temgen\Converter;

/**
 * This convertor converts MD files to HTML
 */
class MD implements \Temgen\Converter
{
	protected $full = "<!DOCTYPE html>
		<html>
			<head>
				<meta charset='utf-8' />
			</head>
			<body>
			{BODY}
			</body>
		</html>";
	
	public function save(\Temgen\Document $document, $filename = null) {
		$parser = new \cebe\markdown\Markdown();
		$result = str_replace("{BODY}", $parser->parse($document->getContents()), $this->full);
		
		if ($filename)
			return file_put_contents($filename, $result);
		return $result;
	}
	
	public function getExtension() {
		return 'html';
	}
}