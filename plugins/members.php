<?php

class PluginMembers extends Plugin
{
	private $members;
	
	public function init()
	{
		$this->loadMembersFile($this->config['File']);
		
		if(empty($this->members))
		{
			$this->members = array('Members' => array(), 'Groups' => array('members' => 'v'));
			$this->saveMembersFile($this->config['File']);
		}
		
	}
	
	public function ServerJoin($msg)
	{
		if($msg['message'] && !$msg['channel'])
			$msg['channel'] = $msg['message'];
		
		if(in_array($msg['user'], $this->getMembersUsers()))
		{
			IRC::message($msg["channel"], "Welcome back, ".$msg['nick']);
			$user = $this->getMember($msg['user']);
			IRC::userMode($msg['nick'], '+'.$this->members['Groups'][$user['group']], $msg['channel']);
		}
	}
	
	public function CommandLeave($cmd, $args)
	{
		if(in_array($cmd['user'], $this->getMembersUsers()))
		{
			$user = $this->getMember($cmd['user']);
			IRC::userMode($cmd['nick'], '-'.$this->members['Groups'][$user['group']]);
			$this->deleteMember($cmd['user']);
			IRC::message($cmd['channel'], 'It\'s sad, but... Goodbye, my friend.');
		}
		else
			IRC::message($cmd['channel'], 'Derp, you have to be registered to leave.');
	}
	
	public function CommandJoin($cmd, $args)
	{
		if(!$this->addMember($cmd['nick'], $cmd['user'], 'members'))
			IRC::message($cmd['channel'], 'Derp, According to my database, you are already a member...');
		else
		{
			IRC::message($cmd['channel'], 'Welcome, '.$cmd['nick']);
			IRC::giveVoice($cmd['nick']);
		}
	}
	
	public function addMember($nick, $host, $group)
	{
		$nick = preg_replace("/[^a-zA-Z0-9]/", '', $nick);
		if(!in_array($host, $this->getMembersUsers()))
		{
			$this->members['Members'][$nick] = array('host' => $host, 'group' => $group);
			$this->saveMembersFile($this->config['File']);
			return TRUE;
		}
		else
			return FALSE;
	}
	
	public function deleteMember($host)
	{
		foreach($this->members['Members'] as $k => $m)
		{
			if($m['host'] == $host)
			{
				unset($this->members['Members'][$k]);
				$this->saveMembersFile($this->config['File']);
				return TRUE;
			}
		}
		
		return FALSE;
	}
	
	public function getMember($host)
	{
		foreach($this->members['Members'] as $m)
		{
			if($m['host'] == $host)
				return $m;
		}
		
		return FALSE;
	}
	
	public function getMembers()
	{
		$users = array();
		foreach($this->members['Members'] as $u)
			$users[] = $u['nick'];
		
		return $users;
	}
	
	public function getMembersUsers($group = NULL)
	{
		$users = array();
		foreach($this->members['Members'] as $u)
		{
			if($group == NULL || $u['group'] == $group)
				$users[] = $u['host'];
		}
		
		return $users;
	}
	
	public function loadMembersFile($file)
	{
		$this->members = Ponybot::parseINIStringRecursive(file_get_contents($file));
	}
	
	public function saveMembersFile($file)
	{
		file_put_contents($file, Ponybot::generateINIStringRecursive($this->members));
	}
}

$this->addPluginData(array(
'name' => 'members',
'className' => 'PluginMembers',
'display' => 'IRC Memberlist plugin',
'dependencies' => array(),
'autoload' => TRUE));
