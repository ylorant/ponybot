<?php

class CoreEvents
{
	private $_main;
	private $_events;
	private $_names;
	
	public function __construct(&$events, &$main)
	{
		$this->_events = $events;
		$this->_main = $main;
		$this->_names = array();
		
		$events->addEvent('server', 'coreevents', '001', array($this, 'ServerConnected'));
		$events->addEvent('server', 'coreevents', 'kick', array($this, 'ServerKick'));
		$events->addEvent('server', 'coreevents', 'ping', array($this, 'ServerPing'));
		$events->addEvent('server', 'coreevents', '353', array($this, 'ServerNamesReply'));
		$events->addEvent('server', 'coreevents', '366', array($this, 'ServerEndOfNames'));
		$events->addEvent('server', 'coreevents', 'mode', array($this, 'ServerMode'));
		$events->addEvent('server', 'coreevents', 'join', array($this, 'ServerJoin'));
		$events->addEvent('server', 'coreevents', 'part', array($this, 'ServerPart'));
	}
	
	public function ServerConnected($command)
	{
		$autoperform = $this->_main->config->getConfig('Servers.'.Server::getName().'.Autoperform');
		
		foreach($autoperform as $action)
			IRC::send($action);
		
		$channels = $this->_main->config->getConfig('Servers.'.Server::getName().'.Channels');
		IRC::joinChannels($channels);
		$this->_main->initialized = TRUE;
	}
	
	public function ServerKick($command)
	{
		if($command['additionnal'][0] == Server::getNick())
			IRC::joinChannel($command['channel']);
	}
	
	public function ServerPing($command)
	{
		IRC::send('PONG :'.$command['additionnal']);
	}
	
	public function ServerJoin($command)
	{
		if($command['nick'] != $this->_main->config->getConfig('Servers.'.Server::getName().'.Nick'))
		{
			if($command['message'] && !$command['channel'])
				$command['channel'] = $command['message'];
			IRC::userJoin($command['channel'], $command['nick']);
		}
	}
	
	public function ServerPart($command)
	{
		IRC::userPart($command['channel'], $command['nick']);
	}
	
	public function ServerMode($command)
	{
		if(preg_match('/(\+|-).*(v|o)/', $command['additionnal'][0], $matches) && isset($command['additionnal'][1]))
		{
			if($matches[1] == '+')
				IRC::userModeAdd($command['channel'], $command['additionnal'][1], $matches[2]);
			else
				IRC::userModeRemove($command['channel'], $command['additionnal'][1], $matches[2]);
		}
	}
	
	public function ServerNamesReply($command)
	{
		if(!isset($this->_names[$command['additionnal'][1]]))
			$this->_names[$command['additionnal'][1]] = array();
		$this->_names[$command['additionnal'][1]] = array_merge($this->_names[$command['additionnal'][1]], explode(' ', $command['message']));
	}
	
	public function ServerEndOfNames($cmd)
	{
		IRC::setChannelUsers($cmd['additionnal'][0], $this->_names[$cmd['additionnal'][0]]);
		unset($this->_names[$cmd['additionnal'][0]]);
	}
}
