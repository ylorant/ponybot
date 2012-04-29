<?php
class PluginIRCBase extends Plugin
{
	public function CommandAction($param, $args)
	{
		IRC::action($param["channel"], join(' ', $args));
	}
	
	public function CommandNames($param)
	{
		IRC::message($param["nick"], join(' ', IRC::getChannelUsers($param["channel"])));
	}
	
	public function CommandSay($param, $args)
	{
		if(!in_array('members', $this->_plugins->getLoadedPlugins()))
			return;
		
		$members = $this->_plugins->getPlugin('members');
		
		if(!$members)
			return;
		
		if(!in_array($param['user'], $members->getMembersUsers('operators')))
			return;
			
		if(in_array($args[0], IRC::getChannels())) 
		{
			$channel = array_shift($args);
			IRC::message($channel, join(' ', $args));
		}
	}
	
	public function CommandEmptyBuffer($param, $args)
	{
		$members = IRC::getChannelRights($param['channel']);
		if(in_array($param['nick'], $members['operator']))
			IRC::emptyBufferMessages($param['channel']);
	}
	
	public function ServerInvite($param)
	{
		if(isset($param['additionnal'][0]))
			$chan = $param['additionnal'][0];
		else
			$chan = $param['message'];
			
		if($param['channel'] == $this->_main->config->getConfig('Servers.'.Server::getName().'.Nick'))
			IRC::joinChannel($chan);
	}
}

$this->addPluginData(array(
'name' => 'ircbase',
'className' => 'PluginIRCBase',
'display' => 'IRC Base plugin',
'dependencies' => array(),
'autoload' => TRUE));
