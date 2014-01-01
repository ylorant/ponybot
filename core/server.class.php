<?php

class ServerInstance
{
	private $_IRC;
	private $_main;
	private $config;
	
	public function __construct(&$main)
	{
		$this->_main = $main;
		$this->_IRC = new IRCConnection();
	}
	
	public function load($config)
	{
		$this->config = $config;
		$this->_name = $this->_main->getServerName($this);
		
		$this->_IRC->connect($config['Address'], $config['Port']);
		$this->_IRC->setNick($config['Nick'], $config['User']);
		//~ if(!isset($config['Ping']) || Ponybot::parseBool($config['Ping']))
			//~ $this->_IRC->waitPing();
		usleep(5000);
		
		if(isset($config['FloodLimit']) && Ponybot::parseRBool($config['FloodLimit']))
			$this->_IRC->setFloodLimit($config['FloodLimit']);
	}
	
	public function getName()
	{
		return $this->_name;
	}
	
	public function getNick()
	{
		return $this->config['Nick'];
	}
	
	public function getIRC()
	{
		return $this->_IRC;
	}
	
	public function step()
	{
		$data = $this->_IRC->read();
		
		foreach($data as $command)
		{
			if(Ponybot::$verbose >= 2)
				echo '['.Server::getName().'] '.$command."\n";
			$command = $this->_IRC->parseMsg($command);
			$this->_main->plugins->callEvent('server', strtolower($command['command']), $command);
			
			if(in_array($command['command'], array('PRIVMSG', 'NOTICE')))
			{
				$message = explode(' ', $command['message']);
				if(strlen($message[0]))
				{
					$message[0] = str_replace(':', '', $message[0]);
					if($message[0] == $this->_main->config->getConfig('Servers.'.Server::getName().'.Nick'))
			        {
						array_shift($message);
						$string = trim(join(' ', $message));
			            $this->_main->plugins->execRegexEvents($command, $string);
			        }
					switch($message[0][0])
					{
						case '!': //Command
							$cmdName = substr(array_shift($message), 1);
							$cmdName = strtolower($cmdName);
							Ponybot::message('Command catched: !$0', array($cmdName), E_DEBUG);
							$this->_main->plugins->callEvent('command', $cmdName, $command, $message);
							break;
						case "\x01": //CTCP
							$cmdName = substr(array_shift($message), 1);
							Ponybot::message('CTCP Command catched: CTCP$0', array($cmdName), E_DEBUG);
							$this->_main->plugins->callEvent('command', 'CTCP.'.$cmdName, $command, $message);
							break;
					}
				}
			}
		}
		$this->_IRC->processBuffer();
	}
}
