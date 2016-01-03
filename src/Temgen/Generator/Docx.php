<?php
namespace Temgen\Generator;

class Docx extends \Temgen\Generator
{
	///@var \Temgen\Document $template template used for generation
	protected $template;
	
	/**
	 * Sets template used for generation
	 *
	 * @param \Temgen\Document $document template
	 */
	public function setTemplate(\Temgen\Document $document) {
		$this->template = $document;
	}
	
	/**
	 * Generates document using specified data
	 *
	 * @param array  $data data for template
	 */
	public function generate($data) {
		//Temporary file
		$path = $this->getUniqueFile();
		
		//Save template body
		$template = $this->template->getDocument();
		
		//Get template body
		$body = new \DOMDocument();
		$body->loadXML($this->clear($this->template->getContents()));
		
		//Now do replacing
		$this->replaceDOM($body, $data);
		
		//Save newly created document
		$this->template->setDocument($body);
		$this->template->save($path);
		
		//Reset template body
		$this->template->setDocument($template);
		
		//Create new document, set it to destroy after freed
		$document = new \Temgen\Document\Docx($path);
		$document->setAutodelete(true);
		
		return $document;
	}
	
	/**
	 * Main recursive replacing function, manages loops and simple replacing
	 *
	 * @throws ParseException  exception thrown when there is any problem with template (usually problem with cycles)
	 * @param \DOMElement $body document to be replaced
	 * @param array $context replacement values
	 */
	protected function replaceDOM($body, $context, $do = true) {		
		//We only search inside text tags
		$texts = $body->getElementsByTagName('t');
		
		//Replace inside texts
		foreach($texts as $element) {
			$matches = array();
			
			//Is there cycle starter here
			if (preg_match($this->regexForeach, $element->nodeValue, $matches)) {
				//Load cycle values
				//@TODO: Warning for inexistent values?
				$replace_array = array();
				if (isset($context[$matches[1]]))
					$replace_array = $context[$matches[1]];
				
				$replace_name = $matches[2];
				
				//Find parent p (always there)
				$parent = $element;
				while(true) {
					$parent = $parent->parentNode;
					if ($parent == null) {
						throw new ParseException('Foreach is incorectly placed.');
					}
					if ($parent->nodeName == 'w:p')
						break;
				}
				
				//Special clause for row
				if ($parent->parentNode->nodeName == 'w:tc' &&
					$parent->parentNode->parentNode->nodeName == 'w:tr') {
					$parent = $parent->parentNode->parentNode;
				}
				
				//Next element
				$next = $parent->nextSibling;
				
				//Find cycle body and ending element
				$to_repeat = $this->getCycleBodyDOM($next, $this->regexForeach, $this->regexForeachEnd);
				
				//When there is any body
				if (count($to_repeat)) {
					//Ending tag element, save for deleting later
					$last = $to_repeat[count($to_repeat)-1]->nextSibling;

					//Repeat elements inside cycles
					foreach($replace_array as $key => $value) {
						//Save values if we're overriding existing
						$tmp1 = isset($context[$replace_name]) ? $context[$replace_name] : null;
						$tmp2 = isset($context['index']) ? $context['index'] : null;
						
						//Load values into context
						$context[$replace_name] = $value;
						$context['index'] = $key;
						
						//Clone elements and append them
						foreach($to_repeat as $item) {
							$item = $item->cloneNode(true);
							$this->replaceDOM($item, $context);
							$parent->parentNode->insertBefore($item, $next);
						}
						
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
					
					//Remove body template
					foreach($to_repeat as $item)
						$parent->parentNode->removeChild($item);
					
					//Remove starting and ending tag
					$parent->parentNode->removeChild($last);
					$parent->parentNode->removeChild($parent);
				} else {
					//Empty cycle ... weird
					$parent->parentNode->removeChild($next);
					$parent->parentNode->removeChild($parent);
				}
				
				//Call this method again, there are some tags unexplored!
				return $this->replaceDOM($body, $context);
			}
			
			//Is there cycle starter here
			if (preg_match($this->regexIf, $element->nodeValue, $matches)) {
				$condition = $matches[1];
				
				//Find parent p (always there)
				$parent = $element;
				while(true) {
					$parent = $parent->parentNode;
					if ($parent == null) {
						throw new ParseException('Foreach is incorectly placed.');
					}
					if ($parent->nodeName == 'w:p')
						break;
				}
				
				//Special clause for row
				if ($parent->parentNode->nodeName == 'w:tc' &&
					$parent->parentNode->parentNode->nodeName == 'w:tr') {
					$parent = $parent->parentNode->parentNode;
				}
				
				//Next element
				$next = $parent->nextSibling;
				
				//Find cycle body and ending element
				$to_repeat = $this->getCycleBodyDOM($next, $this->regexIf, $this->regexIfEnd);
				
				//When there is any body
				if (count($to_repeat)) {
					$conditions = $this->parseTag($context, $condition);
					
					//Ending tag element, save for deleting later
					$last = $to_repeat[count($to_repeat)-1]->nextSibling;

					//Repeat elements inside cycles
					if (!$conditions) {
						//Remove body template
						foreach($to_repeat as $item)
							$parent->parentNode->removeChild($item);
					}
					
					//Remove starting and ending tag
					$parent->parentNode->removeChild($last);
					$parent->parentNode->removeChild($parent);
				} else {
					//Empty cycle ... weird
					$parent->parentNode->removeChild($next);
					$parent->parentNode->removeChild($parent);
				}
				
				//Call this method again, there are some tags unexplored!
				return $this->replaceDOM($body, $context);
			}
			
			$element->nodeValue = $this->replaceText($element->nodeValue, $context);
		}
	}
	
	/**
	 * Finds all elements that are between starting and ending tag
	 *
	 * @throws ParseException when ending tag was not found
	 * @param DOMElement $starter
	 * @return array of DOMElements
	 */
	protected function getCycleBodyDOM($starter, $opening, $closing) {
		$item = $starter;
		$items = array();
		$opened = 1;
		
		while($item != null) {		
			$texts = $item->getElementsByTagName('t');
			foreach($texts as $text) {
				if (preg_match($opening, $text->nodeValue)) {
					$opened++;
				}
				if (preg_match($closing, $text->nodeValue)) {
					$opened--;
					if ($opened == 0) {
						return $items;
					}
				}
			}
			$items[] = $item;
			$item = $item->nextSibling;
		}
		
		throw new ParseException('Ending foreach tag not found.');
	}
	
	/**
	 * Clears XML tags from our template tags
	 *
	 * @param string $body
	 * @return string cleared body
	 */
	protected function clear($body) {
		$offset = 0;
		$copy = $body;
		while(preg_match($this->regexReplace, $body, $matches, PREG_OFFSET_CAPTURE, $offset)) {
			//Entire match
			$match = $matches[0][0];
			//Match position
			$offset = $matches[0][1] + strlen($match);
			$copy = str_replace($match, strip_tags($match), $copy);
		}
		return $copy;
	}
}