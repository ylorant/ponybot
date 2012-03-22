<?php

class PluginMembers extends Plugin
{
	public function eventJoin($msg)
	{
		if(in_array($msg['user'], $this->getMembersOperators()))
		{
				$this->IRC->changeCurrentChan($msg['message']);
				$this->IRC->chanUserMode($msg['nick'], '+o');
		}
		if(in_array($msg['user'], $this->getMembersUsers()))
			$this->IRC->chanUserMode($msg['nick'], '+v');
	}
	
	public function commandLeave($cmd, $args)
	{
		if(in_array($msg['user'], $this->getMembersUsers()))
		{
			$this->deleteMember($msg['user']);
			IRC::chanUserMode($msg['channel'], $msg['nick'], '-v');
			IRC::message('It\'s sad, but... Goodbye, my friend.');
		}
		else
			$this->IRC->chanMsg('Derp, you have to be registered to leave.');
	}
}

$this->addPluginData(array(
'name' => 'members',
'className' => 'PluginMembers',
'display' => 'IRC Memberlist plugin',
'dependencies' => array(),
'autoload' => TRUE));
