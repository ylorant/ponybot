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
		IRC::joinChannels($channels);
	}
	
	public function ServerPing($command)
	{
		IRC::send('PONG :'.$command['additionnal']);
	}
	
	public function CommandSay($parameters, $args)
	{
		Ponybot::message('Say command triggered');
		$to = array_shift($args);
		$message = join(' ', $args);
		IRC::message($to, $message);
	}
	
	public function CommandAction($param, $args)
	{
		IRC::action($param["channel"], join(' ', $args));
	}
}

$this->addPluginData(array(
'name' => 'ircbase',
'className' => 'PluginIRCBase',
'display' => 'IRC Base plugin',
'dependencies' => array(),
'autoload' => TRUE));
