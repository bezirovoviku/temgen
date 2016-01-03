<?php
namespace Temgen\Converter;

/**
 * This convertor uses batch to convert to something
 */
class Composite implements \Temgen\Converter
{
	///@var \Temgen\Converter[] $converters
	protected $converters = [];
	
	///@var string $documentClass class used to construct documents for train
	protected $documentClass = '\Temgen\Document';
	
	/**
	 * @param \Temgen\Converter[] $converters OPTIONAL path to binary
	 */
	public function __construct($converters = null) {
		if ($converters)
			$this->converters = $converters;
	}
	
	/**
	 * Adds new converter
	 *
	 * @param \Temgen\Converter $converter
	 */
	public function add(\Temgen\Converter $converter) {
		$this->converters[] = $converter;
	}
	
	/**
	 * Sets class used to construct documents in composite conversion
	 *
	 * @param string $class document class
	 */
	public function setDocumentClass($class) {
		$this->documentClass = $class;
	}
	
	/**
	 * Converts document to different format.
	 *
	 * @param Document $document document to be converted
	 * @param string $filename target filename, if not set, contents of converted file will be returned
	 * @return bool|string either success of writing file or file contents
	 * @throws \Exception
	 */
	public function save(\Temgen\Document $document, $filename = null) {
		$result = null;
		$original = $document;
		
		for($i = 0; $i < count($this->converters); $i++) {
			$converter = $this->converters[$i];
			$result = $converter->save($document, $filename);
			
			if ($i != count($this->converters) - 1) {
				if ($filename) {
					$document = (new \ReflectionClass($this->documentClass))->newInstance($filename);
					$document->setFilename("document." . $converter->getExtension());
				} else {
					$document = (new \ReflectionClass($this->documentClass))->newInstance();
					$document->setFilename("document." . $converter->getExtension());
					$document->setContents($result);
				}
			}
		}
		
		return $result;
	}
	
	public function getExtension() {
		return count($this->converters) ? end($this->converters)->getExtension() : '';
	}
}