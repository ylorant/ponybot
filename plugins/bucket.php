<?php

class PluginBucket extends Plugin
{
	private $_tabloids = array();
	
	private $grammarChanges = array(
		'i' => 'you',
		'you' => 'I',
		'we' => 'you',
		'your' => 'my',
		'my' => 'your',
		'mine' => 'yours',
		'yours' => 'mine',
		'ours' => 'yours',
		'our' => 'your');
	
	public function init()
	{
		$this->_plugins->addEventListener('bucket', 'Bucket');
		$this->_tabloids = Ponybot::parseINIStringRecursive(file_get_contents($this->config['File']));
		
		$this->addRegexEvent('(.+) is ([^<>].+)', array($this, 'tabloidBindReply'));
		$this->addRegexEvent('(.+) am ([^<>].+)', array($this, 'tabloidBindPluralReply'));
		$this->addRegexEvent('(.+) are ([^<>].+)', array($this, 'tabloidBindPluralReply'));
		$this->addRegexEvent('(.+)(?: is )?<reply>(.+)', array($this, 'tabloidBindDirectReply'));
		$this->addRegexEvent('(.+)(?: is )?<action>(.+)', array($this, 'tabloidBindAction'));
		$this->addRegexEvent('(.+) ?= ?\?', array($this, 'mathEvaluate'));
	}
	
	public function ServerPrivmsg($cmd)
    {
		$msg = explode(' ', $cmd['message']);
        $msg[0] = str_replace(array(',',':'), array('', ''), $msg[0]);
        if($msg[0] == $this->_main->config->getConfig('Servers.'.Server::getName().'.Nick'))
        {
			array_shift($msg);
			$string = trim(join(' ', $msg));
            $this->_plugins->callEvent('bucket', $msg[0]);
            $this->execRegexEvents($cmd, $string);
        }
        else
			$string = trim(join(' ', $msg));
		
		$string = strtolower($string);
        if(isset($this->_tabloids[$string]))
        {
			switch($this->_tabloids[$string]['type'])
			{
				case 'reply':
					IRC::message($cmd['channel'], $this->grammarNazi($this->keywords($string, $cmd)).' is '.$this->keywords($this->_tabloids[$string]['msg'], $cmd));
					break;
				case 'preply':
					IRC::message($cmd['channel'], $this->grammarNazi($this->keywords($string, $cmd)).' are '.$this->keywords($this->_tabloids[$string]['msg'], $cmd));
					break;
				case 'fpreply':
					IRC::message($cmd['channel'], $this->grammarNazi($this->keywords($string, $cmd)).' am '.$this->keywords($this->_tabloids[$string]['msg'], $cmd));
					break;
				case 'dreply':
                    IRC::message($cmd['channel'], $this->keywords($this->_tabloids[$string]['msg'], $cmd));
                    break;
                case 'action':
					IRC::action($cmd['channel'],  $this->keywords($this->_tabloids[$string]['msg'], $cmd));
					break;
			}
		}
    }
    
    public function keywords($msg, $status)
    {
		$msg = str_replace('{me}', $status['nick'], $msg);
		$msg = str_replace('{you}', $this->_main->config->getConfig('Servers.'.Server::getName().'.Nick'), $msg);
		return $msg;
	}
    
    public function tabloidBindAction($cmd, $data)
    {
		$this->_tabloids[strtolower(trim($data[1]))] = array('type' => 'action', 'msg' => trim($this->grammarNazi($data[2])));
		IRC::message($cmd['channel'], 'Okay, got it.');
	}
    
    public function tabloidBindReply($cmd, $data)
    {
		$this->_tabloids[strtolower(trim($data[1]))] = array('type' => 'reply', 'msg' => trim($this->grammarNazi($data[2])));
		IRC::message($cmd['channel'], 'Okay, got it.');
	}
	
    public function tabloidBindPluralReply($cmd, $data)
    {
		if($data[1] == "You")
			$this->_tabloids[strtolower(trim($data[1]))] = array('type' => 'fpreply', 'msg' => trim($this->grammarNazi($data[2])));
		else
			$this->_tabloids[strtolower(trim($data[1]))] = array('type' => 'preply', 'msg' => trim($this->grammarNazi($data[2])));
		IRC::message($cmd['channel'], 'Okay, got it.');
	}
    
    public function tabloidBindDirectReply($cmd, $data)
    {
		$this->_tabloids[strtolower(trim($data[1]))] = array('type' => 'dreply', 'msg' => trim($this->grammarNazi($data[2])));
		IRC::message($cmd['channel'], 'Okay, got it.');
	}
	
	public function grammarNazi($data)
	{
		$data = explode(' ', $data);
		foreach($data as &$word)
		{
			if(isset($this->grammarChanges[strtolower($word)]))
				$word = $this->grammarChanges[strtolower($word)];
		}
		
		return join(' ', $data);
	}
	
	public function mathEvaluate($cmd, $data)
	{
	    $eq = str_split($data[1]);
	    
	    $numbers = array();
	    $operators = array();
	    $current= 0;
	    foreach($eq as $chr)
	    {
	        if(is_numeric($chr))
	            $current = $current * 10 + $chr;
	        elseif($chr == ' ')
	        {
				if($current != 0)
				{
					$numbers[] = $current;
					$current = 0;
				}
	        }
	        elseif(in_array($chr, array('+', '-', '*', '/', '%')))
	            $operators[] = $chr;
	    }
	    
	    $result = 0;
		$input = $numbers[0];
	    foreach($operators as $op)
	    {
	        if($result == 0)
	            $nb1 = array_shift($numbers);
	        else
	            $nb1 = $result;
	        $nb2 = array_shift($numbers);
			$input .= ' '.$op.' '.$nb2;
	        switch($op)
	        {
	            case '+':
	                $result = $nb1 + $nb2;
	                break;
	            case '-':
	                $result = $nb1 - $nb2;
	                break;
	            case '*':
	                $result = $nb1 * $nb2;
	                break;
	            case '/':
	                $result = $nb1 / $nb2;
	                break;
	            case '%':
	                $result = $nb1 % $nb2;
	                break;
	        }
	    }
	    
	    IRC::message($cmd['channel'], $input.' = '.$result);
	}
	
	public function CommandTLoad($msg)
	{
		$this->_tabloids = Ponybot::parseINIStringRecursive(file_get_contents($this->config['File']));
		IRC::message($msg['channel'], "Loaded tabloids.");
	}
	
	public function CommandTSave($msg)
	{
		file_put_contents($this->config['File'], Ponybot::generateINIStringRecursive($this->_tabloids));
		IRC::message($msg['channel'], "Saved tabloids.");
	}
}


$this->addPluginData(array(
'name' => 'bucket',
'className' => 'PluginBucket',
'display' => 'Bucket plugin',
'dependencies' => array(),
'autoload' => TRUE));
