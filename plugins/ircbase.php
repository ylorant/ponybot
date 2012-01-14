<?php
class PluginIRCBase extends Plugin
{
	public function ServerKick($command)
	{
		if($command['additionnal'][0] == Server::getNick())
			IRC::joinChannel($command['channel']);
	}
	
	public function Server376($command)
	{
		$channels = $this->_main->config->getConfig('Servers.'.Server::getName().'.Channels');
		IRC::joinChannels(explode(',',$channels));
	}
	
	public function ServerPing($command)
	{
		IRC::send('PONG :'.$command['additionnal']);
	}
}

$this->addPluginData(array(
'name' => 'ircbase',
'className' => 'PluginIRCBase',
'display' => 'IRC Base plugin',
'dependencies' => array(),
'autoload' => TRUE));
