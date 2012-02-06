<?php
class PluginIRCBase extends Plugin
{
	public function ServerKick($command)
	{
		if($command['additionnal'][0] == Server::getNick())
			IRC::joinChannel($command['channel']);
	}
	
	public function Server372($command)
	{
		$channels = $this->_main->config->getConfig('Servers.'.Server::getName().'.Channels');
		IRC::joinChannels($channels);
	}
	
	public function ServerPing($command)
	{
		IRC::send('PONG :'.$command['additionnal']);
	}
	
	public function CommandSay($parameters)
	{
		Ponybot::message('Say command triggered');
		$to = array_shift($parameters);
		$message = join(' ', $parameters);
		IRC::message($to, $message);
	}
}

$this->addPluginData(array(
'name' => 'ircbase',
'className' => 'PluginIRCBase',
'display' => 'IRC Base plugin',
'dependencies' => array(),
'autoload' => TRUE));
