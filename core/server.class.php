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
		if(!isset($config['Ping']) || Ponybot::parseBool($config['Ping']))
			$this->_IRC->waitPing();
		usleep(5000);
		$this->_IRC->setFloodLimit(true);
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
				if($message[0][0] == '!')
				{
					$cmdName = substr(array_shift($message), 1);
					Ponybot::message('Command catched: !$0', array($cmdName), E_DEBUG);
					$this->_main->plugins->callEvent('command', $cmdName, $command, $message);
				}
			}
		}
		$this->_IRC->processBuffer();
	}
}
