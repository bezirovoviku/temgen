<?php
namespace Temgen;

/**
 * This class manages generation of individual documents
 */
class Generator
{
	///@var Document $template template used for generation
	protected $template;
	
	///@var string $tmp path to tmp folder
	protected $tmp;
	
	///@var string $regexForeach regular expression used to find foreach
	protected $regexForeach = '/{\s*foreach\s+([^\s]*)\s+as\s+([^\s]*)\s*}/i';
	///@var string $regexForeachEnd regular expression used to find foreach end
	protected $regexForeachEnd = '/{\s*\/foreach\s*}/i';
	///@var string $regexIf regular expression used to find if
	protected $regexIf = '/{\s*if\s+([^}]*)}/i';
	///@var string $regexIfEnd regular expression used to find if end
	protected $regexIfEnd = '/{\s*\/if\s*}/i';
	///@var string $regexForeach regular expression used to find any template tag
	protected $regexReplace = '/\{([^\/][^}]*)\}/';
	
	///@var \Temgen\Generator\Filter[] $filters available replacement filters
	protected $filters = array();
		
	/**
	 * Sets template used for generation
	 *
	 * @param Document $template
	 */
	public function setTemplate(Document $template) {
		$this->template = $template;
	}

	/**
	 * Automatically adds basic filters
	 */
	public function addFilters() {
		$this->addFilter(new Generator\Filters\Upper);
		$this->addFilter(new Generator\Filters\Lower);
		$this->addFilter(new Generator\Filters\Date);
		$this->addFilter(new Generator\Filters\Number);
	}
	
	/**
	 * Adds new filter to generator
	 *
	 * @param \Temgen\Generator\Filter $filter filter added
	 */
	public function addFilter($filter) {
		$this->filters[$filter->getTag()] = $filter;
	}
	
	/**
	 * Tries to find filter by its tag
	 *
	 * @param string $tag filter tag
	 * @return \Temgen\Generator\Filter|null
	 */
	public function getFilter($tag) {
		return isset($this->filters[$tag]) ? $this->filters[$tag] : null;
	}
	
	/**
	 * Sets tmp path used for generating archive
	 *
	 * @param string $tmp path to tmp folder
	 */
	public function setTmp($tmp) {
		$this->tmp = $tmp;
	}
	
	/**
	 * Generates document using specified data
	 *
	 * @param array  $data data for template
	 * @return Document generated document
	 */
	public function generate($data) {
		$document = new Document();
		$document->setContents($this->replace($this->template->getContents(), $data));
		return $document;
	}
	
	/**
	 * Generates archive of documents based on specified data.
	 *
	 * @param array  $data array where each element is data for one document
	 * @param string $path where to save generated archive
	 * @param Converter $converter OPTIONAL document converter
	 */
	public function generateArchive($data, $path, $converter = null) {
		//Files to be deleted after creating archive
		$cleanup = array();
		
		//Create directory if nonexistent
		@mkdir(dirname($path), 0770, true);

		//Prepare result archive
		$archive = new \ZipArchive();
		if (!$archive->open($path, \ZipArchive::CREATE)) {
			throw new \Exception("Failed to open result archive.");
		}

		//Create documents and add them to archive
		foreach($data as $index => $docdata) {
			$ext = $this->template->getExtension();
			
			//Generate file
			$file = "{$this->tmp}/document$index.$ext";
			$document = $this->generate($docdata);
			
			//Convert if needed
			if ($converter) {
				$converter->save($document, $file);
				$ext = $converter->getExtension();
			} else {
				$document->save($file);
			}
			
			//Remove this file after we're done
			$cleanup[] = $file;
			
			//Put to archive
			$archive->addFile($file, "document$index.$ext");
		}
		
		//Write documents to archive
		$archive->close();
		
		//Remove temp files, has to be done after closing archive
		foreach($cleanup as $file)
			unlink($file);

		//Check if archive was really created
		if (!file_exists($path)) {
			throw new \Exception("Failed to create result archive.");
		}
	}
	
	/**
	 * Main recursive replacing function, manages loops and simple replacing
	 *
	 * @throws ParseException exception thrown when there is any problem with template (usually problem with cycles)
	 * @param string $body document to be replaced
	 * @param array $context replacement values
	 * @return string replaced text
	 */
	protected function replace($body, $context) {
		$matches = array();

		//Is there cycle starter here
		if (preg_match($this->regexForeach, $body, $matches, PREG_OFFSET_CAPTURE)) {
			//Load cycle values
			//@TODO: Warning for inexistent values?
			$replace_array = array();
			if (isset($context[$matches[1][0]]))
				$replace_array = $context[$matches[1][0]];
			
			//Name of item inside body
			$replace_name = $matches[2][0];
			
			//Find cycle body and ending element
			$to_repeat = $this->getCycleBody($body, $matches[0][1] + strlen($matches[0][0]), $this->regexForeach, $this->regexForeachEnd);
			$replacement = "";
			
			//When there is any body
			if ($to_repeat->body) {
				//Repeat elements inside cycles
				foreach($replace_array as $key => $value) {
					//Save values if we're overriding existing
					$tmp1 = isset($context[$replace_name]) ? $context[$replace_name] : null;
					$tmp2 = isset($context['index']) ? $context['index'] : null;
					
					//Load values into context
					$context[$replace_name] = $value;
					$context['index'] = $key;
					
					$replacement .= $this->replace($to_repeat->body, $context);
					
					//Restore or clean values
					if ($tmp1 == null)
						unset($context[$replace_name]);
					else
						$context[$replace_name] = $tmp1;
					
					if ($tmp2 == null)
						unset($context['index']);
					else
						$context['index'] = $tmp2;
				}
				
			}
			
			$body = str_replace($matches[0][0] . $to_repeat->all, $replacement, $body);
						
			return $this->replace($body, $context);
		}
		
		if (preg_match($this->regexIf, $body, $matches, PREG_OFFSET_CAPTURE)) {
			$conditions = $matches[1][0];
			
			$content = $this->getCycleBody($body, $matches[0][1] + strlen($matches[0][0]), $this->regexIf, $this->regexIfEnd);
			if ($this->parseTag($context, $conditions)) {
				$body = str_replace($matches[0][0] . $content->all, $content->body, $body);
			} else {
				$body = str_replace($matches[0][0] . $content->all, "", $body);
			}
			
			return $this->replace($body, $context);
		}
		
		return $this->replaceText($body, $context);
	}
	
	/**
	 * Actually replaces any template values inside text
	 *
	 * @param string $body    text
	 * @param array  $context replacing context (what to replace)
	 * @return string text with context replaced
	 */
	protected function replaceText($body, $context) {
		while(preg_match($this->regexReplace, $body, $matches)) {
			//Entire match
			$match = $matches[0];
			//Only tag content
			$content = $matches[1];
			
			//Get tag result
			$value = $this->parseTag($context, $content);
			
			//Replace the tag
			$body = str_replace($match, $value, $body);
		}
		
		return $body;
	}
	
	/**
	 * Parse tag contents and return tag value
	 *
	 * @param array  $context replacing context
	 * @param string $tag     tag content
	 * @return string parset tag value
	 */
	protected function parseTag($context, $tag) {
		//@TODO: str_getcsv feels bad
		
		//Split to pipes
		//$tags = str_getcsv(trim($tag), '|');
		preg_match_all('/\*(?:\\\\.|[^\\\\\*])*\*|[^|]+/', $tag, $matches);
		$tags = $matches[0];
		
		//Resulting value
		$out = null;
		
		//Go trought pipes, pass value along
		foreach($tags as $tag) {
			//Split to arguments
			//$tag = str_getcsv(trim($tag), ' ');
			preg_match_all('/\*(?:\\\\.|[^\\\\\*])*\*|\S+/', $tag, $matches);
			$tag = $matches[0];
			
			//Basic method or just variable name
			$filter = array_shift($tag);
			
			//Temp value to store filter object if found
			$obj = null;
			
			//If the first arguments isn't first name
			if (substr($filter, 0, 1) == '$' || ($obj = $this->getFilter($filter)) === null) {
				//No arguments, this is probably just variable
				if (count($tag) == 0) {
					$out = $this->findVariable($context, $filter);
					continue;
				}
				
				throw new ParseException("There is no such filter '$filter'");
			}
			
			//Store filter object into right variable
			$filter = $obj;
			
			//Prepare filter arguments
			$arguments = array();
			foreach($tag as $arg) {
				//Not spaces needed!
				$arg = trim(trim($arg), '*');
				
				//If its variable (variables as arguments must have variable sign)
				if (substr($arg, 0, 1) == '$')
					$arg = $this->findVariable($context, substr($arg, 1));
				
				$arguments[] = $arg;
			}
			
			//Play filer
			$out = $filter->filter($this, $context, $arguments, $out);
		}
		
		return $out;
	}
	
	/**
	 * Tries to find variable value by its name
	 *
	 * @param array  $context  replacing context
	 * @param string $variable variable name
	 * @return string|null value or null if not found
	 */
	protected function findVariable($context, $variable) {
		//Remove dolar sign if present
		if (substr($variable, 0, 1) == '$')
			$variable = substr($variable, 1);
		
		//Split by dots
		$parts = explode('.', $variable);
		
		//Find actual value in nested objects if needed
		$value = $context;
		foreach($parts as $part) {
			if (isset($value[$part])) {
				$value = $value[$part];
			} else {
				return null;
			}
		}
		
		return $value;
	}
	
	/**
	 * Finds string between starting and ending tag
	 *
	 * @throws ParseException when ending tag was not found
	 * @param string $body body to find
	 * @param int $from from where to search
	 * @param string $opening regexp matching opening tag
	 * @param string $closing regexp matching closing tag
	 * @return object body and body with ending tag
	 */
	protected function getCycleBody($body, $from, $opening, $closing) {
		$index = $from;
		$opened = 1;
		$change = true;
		
		while($change) {
			$change = false;
			
			if (preg_match($opening, $body, $matches, PREG_OFFSET_CAPTURE, $index)) {
				$opened++;
				$index = $matches[0][1] + strlen($matches[0][0]);
				$change = true;
			}
			
			if (preg_match($closing, $body, $matches, PREG_OFFSET_CAPTURE, $index)) {
				$opened--;
				if ($opened == 0) {
					$index = $matches[0][1];
					return (object)array(
						'body' => substr($body, $from, $index - $from),
						'all' => substr($body, $from, $index + strlen($matches[0][0]) - $from)
					);
				}
				$index = $matches[0][1] + strlen($matches[0][0]);
				$change = true;
			}
		}
		
		throw new ParseException('Ending foreach tag not found.');
	}
	
	/**
	 * Returns unique temp filename
	 *
	 * @param string $prefix temp file prefx
	 * @param string $ext temp file extension
	 * @return string unused unique path
	 */
	protected function getUniqueFile($prefix = 'temp', $ext = null) {
		$index = mt_rand(1E2,1E5);
		$file = null;
		do {
			$file = "{$this->tmp}/$prefix$index$ext";
			$index++;
		} while (file_exists($file));
		return $file;
	}
}