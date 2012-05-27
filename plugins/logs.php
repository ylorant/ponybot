<?php

class PluginLogs extends Plugin
{
	private $logfiles = array();
	
	public function init()
	{
		if(!isset($this->config['BaseDir']))
			$this->config['BaseDir'] = 'logs';
		
		if(!is_dir($this->config['BaseDir']))
			mkdir($this->config['BaseDir']);
		
		if(isset($this->config['Whitelist']))
			$this->config['Whitelist'] = explode(',', $this->config['Whitelist']);
		if(isset($this->config['Blacklist']))
			$this->config['Blacklist'] = explode(',', $this->config['Blacklist']);
		
		
				
		 
	}
	
	public function Server001()
	{
		if(!is_dir($this->config['BaseDir'].'/'.$server))
			mkdir($this->config['BaseDir'].'/'.$server);
		
		$server = Server::getName();
		$nick = $this->_main->config->getConfig('Servers.'.$server.'.Nick');
		$this->logfiles[$server.'.'.$nick] = fopen($this->config['BaseDir'].'/'.$server.'/'.$nick.'.log', 'a+');
	}
	
	public function ServerJoin($cmd)
	{
		$server = Server::getName();
		if($this->banlistCheck($server, $cmd['channel']))
		{
			if($cmd['nick'] == $this->_main->config->getConfig('Servers.'.$server.'.Nick'))
			{
			
				if(!is_dir($this->config['BaseDir'].'/'.$server))
					mkdir($this->config['BaseDir'].'/'.$server);
				
				$this->logfiles[$server.'.'.$cmd['channel']] = fopen($this->config['BaseDir'].'/'.$server.'/'.$cmd['channel'].'.log', 'a+'); 
			}
		
			fputs($this->logfiles[$sever.'.'.$cmd['channel']], '--> '.$cmd['nick'].' ('.$cmd['user'].') joined the channel.');
		}
	}
	
	public function ServerPart($cmd)
	{
		$server = Server::getName();
		
		if($this->banlistCheck($server, $cmd['channel']))
		{
			fputs($this->logfiles[$sever.'.'.$cmd['channel']], '<-- '.$cmd['nick'].' ('.$cmd['user'].') left the channel.');
		
			if($cmd['nick'] == $this->_main->config->getConfig('Servers.'.$server.'.Nick'))
				fclose($this->logfiles[$server.'.'.$cmd['channel']]);
		}
	}
	
	public function ServerKick($cmd)
	{
		$server = Server::getName();
		
		if($this->banlistCheck($server, $cmd['channel']))
		{
			fputs($this->logfiles[$sever.'.'.$cmd['channel']], '<-- '.$cmd['nick'].' ('.$cmd['user'].') was kicked from the channel.');
		
			if($cmd['nick'] == $this->_main->config->getConfig('Servers.'.$server.'.Nick'))
				fclose($this->logfiles[$server.'.'.$cmd['channel']]);
		}
	}
	
	public function ServerMode($cmd)
	{
		$server = Server::getName();
		
		if($this->banlistCheck($server, $cmd['channel']))
			fputs($this->logfiles[$sever.'.'.$cmd['channel']], '<-- '.$cmd['nick'].' set mode ['.join(' ', $cmd['additionnal']).'].');
	}
	
	public function ServerPrivmsg($cmd)
	{
		if($this->banlistCheck($server, $cmd['channel']))
			fputs($this->logfiles[$sever.'.'.$cmd['channel']], '<'.$cmd['nick'].'> '.$cmd['message']);
	}
	
	private function banlistCheck($server, $channel)
	{
		if((isset($this->config['Whitelist']) && in_array($server.'/'.$channel, $this->config['Whitelist'])) || (isset($this->config['Blacklist']) && !in_array($server.'/'.$channel, $this->config['Blacklist'])))
			return TRUE;
		
		return FALSE;
	}
}


$this->addPluginData(array(
'name' => 'logs',
'className' => 'PluginLogs',
'display' => 'IRC Log plugin',
'dependencies' => array(),
'autoload' => TRUE));
