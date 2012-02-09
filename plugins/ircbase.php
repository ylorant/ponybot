<?php
class PluginIRCBase extends Plugin
{
	public function CommandSay($parameters, $args)
	{
		$to = array_shift($args);
		$message = join(' ', $args);
		IRC::message($to, $message);
	}
	
	public function CommandAction($param, $args)
	{
		IRC::action($param["channel"], join(' ', $args));
	}
	
	public function CommandNames($param)
	{
		IRC::message($param["channel"], join(' ', IRC::getChannelUsers($param["channel"])));
	}
}

$this->addPluginData(array(
'name' => 'ircbase',
'className' => 'PluginIRCBase',
'display' => 'IRC Base plugin',
'dependencies' => array(),
'autoload' => TRUE));
