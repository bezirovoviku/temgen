<?php
namespace Temgen\Converter;

/**
 * This convertor uses phantomjs to convert to pdf
 */
class PPDF extends Batch
{
	public function __construct($path = 'phantomjs') {
		parent::__construct($path);
	}
	
	protected function getCommand($import, $export, $temp) {
		return escapeshellarg($this->path);
	}
	
	public function getExtension() {
		return 'pdf';
	}
}