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
		
		$events->addEvent('server', 'coreevents', '376', array($this, 'ServerMOTD'));
		$events->addEvent('server', 'coreevents', 'kick', array($this, 'ServerKick'));
		$events->addEvent('server', 'coreevents', 'ping', array($this, 'ServerPing'));
		$events->addEvent('server', 'coreevents', '353', array($this, 'ServerNamesReply'));
		$events->addEvent('server', 'coreevents', '366', array($this, 'ServerEndOfNames'));
		
	}
	
	public function ServerMOTD($command)
	{
		$channels = $this->_main->config->getConfig('Servers.'.Server::getName().'.Channels');
		IRC::joinChannels($channels);
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
	
	public function ServerNamesReply($command)
	{
		if(!isset($this->_names[$command['additionnal'][1]]))
			$this->_names[$command['additionnal'][1]] = array();
		$this->_names[$command['additionnal'][1]] = array_merge($this->_names[$command['additionnal'][1]], explode(' ', $command['message']));
	}
	
	public function ServerEndOfNames($command)
	{
		IRC::setChannelUsers($command['additionnal'][0], $this->_names[$command['additionnal'][0]]);
		unset($this->_names[$command['additionnal'][0]]);
	}
}
