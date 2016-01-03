<?php
namespace Temgen\Converter;

/**
 * This convertor uses batch to convert to something
 */
class Batch implements \Temgen\Converter
{
	///@var string $path path to binary 
	protected $path;
	
	/**
	 * @param string $path OPTIONAL path to binary
	 */
	public function __construct($path = '/dev/null') {
		$this->path = $path;
	}
	
	/**
	 * Creates temporary path
	 *
	 * @param string $dir root where path should be made
	 * @param string $prefix prefix for created path
	 * @return string temporary path
	 */
	protected function tempdir($dir = false, $prefix = 'opdf') {
		$tempfile = tempnam($dir ? $dir : sys_get_temp_dir(), $prefix);
		if (file_exists($tempfile)) {
			unlink($tempfile);
		}
		mkdir($tempfile);
		if (is_dir($tempfile)) {
			return $tempfile;
		}
		return null;
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
		//Prepare temp path, which will be used to store files for openoffice
		$temp = $this->tempdir();
	
		if (!$temp || !is_dir($temp)) {
			throw new \Exception("Failed to create temp path.");
		}
		
		//Prepare correct paths 
		$import = "$temp/document." . $document->getExtension();
		$export = "$temp/document." . $this->getExtension();

		//Save document, so command can use it
		if (!$document->save($import))
			throw new \Exception("Failed to export document.");
		
		//Call batch
		$command = "cd " . escapeshellarg(__DIR__) . " && " . $this->getCommand($import, $export, $temp);
		exec($command, $output, $code);
		
		//Handle error outputs
		if ($code != 0) {
			//Clean after us
			unlink($import);
			unlink($export);
			rmdir($temp);
			
			throw new \Exception("Failed to convert document. Error: $code. Command: $command");
		}
		
		//Remove original document
		unlink($import);
		
		//Save to file or return converted contents
		if ($filename) {
			//Move exported to target path
			if (!rename($export, $filename)) {
				//Cleanup
				unlink($export);
				rmdir($temp);
				
				throw new \Exception("Failed to move result document to target.");
			}
			
			//Remove empty directory
			rmdir($temp);
			
			return true;
		} else {
			//Load exported contents, so we can delete it
			$content = file_get_contents($export);
			
			//Cleanup
			unlink($export);
			rmdir($temp);
			
			//Return actual content
			return $content;
		}
	}
	
	/**
	 * Returns command that should be called.
	 *
	 * @param string $import path to file that is impored
	 * @param string $export path to file that should be exported
	 * @param string $temp   temp path created for this conversion
	 * @return string command
	 */
	protected function getCommand($import, $export, $temp) {
		return escapeshellarg($this->path) . " " . escapeshellarg($import) . " " . escapeshellarg($export);
	}
	
	public function getExtension() {
		return '';
	}
}