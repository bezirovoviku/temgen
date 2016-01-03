<?php
namespace Temgen\Converter;

/**
 * This convertor uses openoffice/libreoffice headless to convert to pdf
 */
class OPDF extends Batch
{
	public function __construct($path = 'soffice') {
		parent::__construct($path);
	}
	
	protected function getCommand($import, $export, $temp) {
		return escapeshellarg($this->path) . " --headless -convert-to pdf " . escapeshellarg($import) . " -outdir " . escapeshellarg($temp);
	}
	
	public function getExtension() {
		return 'pdf';
	}
}