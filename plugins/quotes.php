<?php

class PluginQuotes extends Plugin
{
	private $_lastQuoteTime = 0;
	private $_savedQuotes;
	
	public function init()
	{
		$this->_savedQuotes = array();
		$this->loadQuotes();
		
		$this->addRegexEvent('quote(?:.*) ([0-9]+)(?:x([0-9]+))? (?:.*)([0-9]+(?::[0-9]+)?) (?:.*)([0-9]+(?::[0-9]+)?)(?: (?:.*)([a-z]+))?', array($this, 'sayIntervalQuote'));
		$this->addRegexEvent('quote(?:.*) ([0-9]+)(?:x([0-9]+))? (?:.*)([0-9]+(?::[0-9]+)?)(?: (?:.*)([a-z]+))?', array($this, 'sayDirectQuote'));
		
		$this->addRegexEvent('quote "([a-zA-Z0-9]+)"(?: (?:.*)([a-z]+))?', array($this, 'saySavedQuote'));
		$this->addRegexEvent('save quote(?:.*) ([0-9]+)(?:x([0-9]+))? (?:.*)([0-9]+(?::[0-9]+)?) (?:.*)([0-9]+(?::[0-9]+)?) .*"([a-zA-Z0-9]+)"', array($this, 'saveIntervalQuote'));
		$this->addRegexEvent('save quote(?:.*) ([0-9]+)(?:x([0-9]+))? (?:.*)([0-9]+(?::[0-9]+)?) .*"([a-zA-Z0-9]+)"', array($this, 'saveDirectQuote'));
		$this->addRegexEvent('list quotes', array($this, 'listQuotes'));
		$this->addRegexEvent('delete quote "([a-zA-Z0-9]+)"', array($this, 'deleteQuote'));
	}
	
	public function deleteQuote($cmd, $data)
	{
		if(!isset($this->_savedQuotes[$data[1]]))
			return IRC::message($cmd['channel'], 'Derp, this quote name doesn\'t exists.');
		
		unset($this->_savedQuotes[$data[1]]);
		$this->saveQuotes();
		IRC::message($cmd['channel'], 'Quote deleted');
	}
	
	public function listQuotes($cmd, $data)
	{
		IRC::message($cmd['channel'], 'List of all saved quotes:');
		foreach($this->_savedQuotes as $n => $q)
			IRC::message($cmd['channel'], $n.' - '.$q['season'].'x'.$q['ep'].', '.$q['start'].($q['end'] != -1 ? ' to '.$q['end'] : ''));
	}
	
	public function saySavedQuote($cmd, $data)
	{
		if($this->_lastQuoteTime >= time())
			return;
		
		if(!isset($this->_savedQuotes[$data[1]]))
			return IRC::message($cmd['channel'], 'Derp, i don\'t have this quote, brony.');
		
		$data[2] = !isset($data[2]) ? '' : $data[2];
		list($lang, $season, $ep) = $this->getQuoteData($data[2], $this->_savedQuotes[$data[1]]['season'], $this->_savedQuotes[$data[1]]['ep']);
		
		if(!($srt = $this->_parseSrt($lang, $season, $ep, $this->_savedQuotes[$data[1]]['start'], $this->_savedQuotes[$data[1]]['end'], $cmd['channel'])))
			IRC::message($cmd['channel'], 'Derp, i don\'t have this quote, brony.');
		else
			$this->_lastQuoteTime = IRC::getLastBufferTime();
	}
	
	public function sayIntervalQuote($cmd, $data)
	{
		if($this->_lastQuoteTime >= time())
			return;
		
		//~ if(empty($data[3]))
			//~ return IRC::message($cmd['message'], 'Derp, I don\'t know what i have to show to you, dumbass.');
		
		$data[5] = !isset($data[5]) ? '' : $data[5];
		
		list($lang, $season, $ep) = $this->getQuoteData($data[5], $data[1], $data[2]);
		
		if(!($srt = $this->_parseSrt($lang, $season, $ep, $data[3], $data[4], $cmd['channel'])))
			IRC::message($cmd['channel'], 'Derp, i don\'t have this quote, brony.');
		else
			$this->_lastQuoteTime = IRC::getLastBufferTime();
	}
	
	public function sayDirectQuote($cmd, $data)
	{
		if($this->_lastQuoteTime >= time())
			return;
		//~ 
		//~ if(empty($data[3]))
			//~ return IRC::message($cmd['message'], 'Derp, I don\'t know what i have to show to you, dumbass.');
		//~ 
		$data[4] = !isset($data[4]) ? '' : $data[4];
		
		list($lang, $season, $ep) = $this->getQuoteData($data[4], $data[1], $data[2]);
		
		if(!($srt = $this->_parseSrt($lang, $season, $ep, $data[3], -1, $cmd['channel'])))
			IRC::message($cmd['channel'], 'Derp, i don\'t have this quote, brony.');
		else
			$this->_lastQuoteTime = IRC::getLastBufferTime();
	}
	
	public function saveDirectQuote($cmd, $data)
	{
		list($lang, $season, $ep) = $this->getQuoteData('english', $data[1], $data[2]);
		
		if(!($srt = $this->_parseSrt($lang, $season, $ep, $data[3], -1, null, false)))
			IRC::message($cmd['channel'], 'Derp, i don\'t have this quote, brony.');
		elseif(isset($this->_savedQuotes[$data[4]]))
			IRC::message($cmd['channel'], 'Derp, there is already a quote with this name.');
		else
		{
			$this->_savedQuotes[$data[4]] = array('season' => $season, 'ep' => $ep, 'start' => $data[3], 'end' => -1);
			$this->saveQuotes();
			IRC::message($cmd['channel'], "Quote saved.");
		}
	}
	
	public function saveIntervalQuote($cmd, $data)
	{
		list($lang, $season, $ep) = $this->getQuoteData('english', $data[1], $data[2]);
		
		if(!($srt = $this->_parseSrt($lang, $season, $ep, $data[3], $data[4], null, false)))
			IRC::message($cmd['channel'], 'Derp, i don\'t have this quote, brony.');
		elseif(isset($this->_savedQuotes[$data[4]]))
			IRC::message($cmd['channel'], 'Derp, there is already a quote with this name.');
		else
		{
			$this->_savedQuotes[$data[5]] = array('season' => $season, 'ep' => $ep, 'start' => $data[3], 'end' => $data[4]);
			$this->saveQuotes();
			IRC::message($cmd['channel'], "Quote saved.");
		}
	}
	
	public function getQuoteData($l, $s, $e)
	{
		if(!empty($l))
		{
			switch(strtolower($l))
			{
				case 'french':
				case 'français':
					$lang = 'FR';
					break;
				
				case 'portugese':
				case 'portuguais':
					$lang = 'PT';
					break;
				case 'russian':
				case 'russe':
					$lang = 'RU';
					break;
				case 'english':
				case 'anglais':
				default:
					$lang = 'EN';
					break;
			}
		}
		else
			$lang = 'EN';
		
		if(!empty($e))
		{
			$season = $s;
			$ep = $e;
		}
		else
		{
			$season = '1';
			$ep = $s;
		}
		
		return array($lang, $season, $ep);
	}
	
	public function loadQuotes()
	{
		$this->_savedQuotes = Ponybot::parseINIStringRecursive(file_get_contents($this->config['File']));
	}
	
	public function saveQuotes()
	{
		file_put_contents($this->config['File'], Ponybot::generateINIStringRecursive($this->_savedQuotes));
	}
	
	private function _parseSrt($lang, $season, $ep, $from, $to = -1, $channel = null, $send = TRUE)
	{
		var_dump($lang);
		var_dump($season);
		var_dump($ep	);
		var_dump($from);
		var_dump($to);
		if($ep < 10)
			$ep = '0'.$ep;
		
		$fromarray = explode(':', $from);
		$fromarray = array_reverse($fromarray);
		$from = 0;
		foreach($fromarray as $mul => $el)
			$from += pow(60, $mul) * $el;
			
		$toarray = explode(':', $to);
		$toarray = array_reverse($toarray);
		$to = 0;
		foreach($toarray as $mul => $el)
			$to += pow(60, $mul) * $el;
		
		if(is_file('srt/'.$season.'/MLP '.$lang.' '.$ep.'.srt'))
		{
			$srt = file_get_contents('srt/'.$season.'/MLP '.$lang.' '.$ep.'.srt');
			$srt = explode("\r\n\r\n", $srt);
			$retr = FALSE;
			foreach($srt as &$row)
			{
				$row = explode("\n", $row, 3);
				
				$row[1] = explode(' --> ', $row[1]);
				$row[1] = explode(',', $row[1][0]);
				$row[1] = explode(':', $row[1][0]);
				$time = $row[1];
				array_shift($time);
				$time = join(':', $time);
				$row[1] = 3600 * $row[1][0] + 60 * $row[1][1] + $row[1][2];
				if($row[1] >= $from)
				{
					
					if(!$retr)
						$retr = $row[1];
					
					$row[2] = explode("\n", $row[2]);
					foreach($row[2] as $i => $line)
					{
						if($send)
						{
							$line = preg_replace('#<.*>(.*)</.*>#isU', "\002$1\002", $line);
							IRC::timedMessage($channel, '['.$time.'] '.$line, (($row[1] - $retr) + time()+1+$i));
						}
					}
					if($to > $from)
					{
						if($row[1] >= $to)
							break;
					}
					else
						break;
				}
			}
			
			return TRUE;
			
		}
		else
			return FALSE;
	}
}

$this->addPluginData(array(
'name' => 'quotes',
'className' => 'PluginQuotes',
'display' => 'IRC Quotes plugin',
'dependencies' => array(),
'autoload' => TRUE));
