<?php

class PluginMembers extends Plugin
{
	private $members;
	
	public function init()
	{
		$this->loadMembersFile($this->config['File']);
		
		if(empty($this->members))
		{
			$this->members = array('Members' => array(), 'Groups' => array('members' => 'v', 'operators' => 'o'));
			$this->saveMembersFile($this->config['File']);
		}
		
	}
	
	public function ServerJoin($msg)
	{
		if($msg['message'] && !$msg['channel'])
			$msg['channel'] = $msg['message'];
		
		if(in_array($msg['user'], $this->getMembersUsers()))
		{
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
	
	public function CommandAddGrp($cmd, $msg)
	{
		if(!isset($msg[1]))
			return IRC::message($cmd['channel'], "Not enough parameters.");
		
		$user = $this->getMember($cmd['user']);
		if(!$user || $user['group'] != 'operators')
			return IRC::message($cmd['channel'], "Insufficient privileges.");
		
		if($this->addGroup($msg[0], $msg[1]))
			IRC::message($cmd['channel'], "Group added.");
		else
			IRC::message($cmd['channel'], "This group already exists.");
	}
	
	public function CommandDelGrp($cmd, $msg)
	{
		if(empty($msg))
			return IRC::message($cmd['channel'], "Not enough parameters.");
		
		$user = $this->getMember($cmd['user']);
		if(!$user || $user['group'] != 'operators')
			return IRC::message($cmd['channel'], "Insufficient privileges.");
		
		if($this->deleteGroup($msg[0]))
			IRC::message($cmd['channel'], "Group deleted.");
		else
			IRC::message($cmd['channel'], "This group does not exists.");
	}
	
	public function CommandChgrp($cmd, $msg)
	{
		if(!isset($msg[1]))
			return IRC::message($cmd['channel'], "Not enough parameters.");
		
		$user = $this->getMember($cmd['user']);
		
		if(!$user || $user['group'] != 'operators')
			return IRC::message($cmd['channel'], "Insufficient privileges.");
		
		$info = IRC::whois($msg[0]);
		
		if(!$info)
			return IRC::message($cmd['channel'], "No such nick.");
		
		$user = $this->getMember($info['user'].'@'.$info['host']);
		$name = $this->getMemberName($info['user'].'@'.$info['host']);
		
		if(!$user)
			IRC::message($cmd['channel'], "Non-existent member.");
		else
		{
			$oldgroup = $this->members['Members'][Server::getName()][$name]['group'];
			if($this->setGroup($this->members['Members'][Server::getName()][$name]['host'], $msg[1]))
			{
				IRC::userMode($msg[0], '-'.$this->members['Groups'][$oldgroup]);
				IRC::userMode($msg[0], '+'.$this->members['Groups'][$msg[1]]);
				IRC::message($cmd['channel'], "Group changed.");
			}
		}
	}
	
	public function addMember($nick, $host, $group)
	{
		$nick = preg_replace("/[^a-zA-Z0-9]/", '', $nick);
		if(!in_array($host, $this->getMembersUsers()))
		{
			$this->members['Members'][Server::getName()][$nick] = array('host' => $host, 'group' => $group);
			$this->saveMembersFile($this->config['File']);
			return TRUE;
		}
		else
			return FALSE;
	}
	
	public function addGroup($group, $mode)
	{
		if(!isset($this->members['Groups'][$group]))
		{
			$this->members['Groups'][$group] = $mode;
			$this->saveMembersFile($this->config['File']);
		}
		else
			return FALSE;
		
		return TRUE;
	}
	
	public function deleteGroup($group)
	{
		if(isset($this->members['Groups'][$group]))
		{
			unset($this->members['Groups'][$group]);
			foreach($this->members['Members'][Server::getName()] as &$m)
			{
				if($m['group'] == $group)
					$m['group'] = 'members';
			}
			$this->saveMembersFile($this->config['File']);
			return TRUE;
		}
		else
			return FALSE;
	}
	
	public function setGroup($host, $group)
	{
		if(!isset($this->members['Groups'][$group]))
			return FALSE;
		
		foreach($this->members['Members'][Server::getName()] as &$m)
		{
			if($m['host'] == $host)
			{
				$m['group'] = $group;
				$this->saveMembersFile($this->config['File']);
				return TRUE;
			}
		}
		
		return FALSE;
	}
	
	public function deleteMember($host)
	{
		foreach($this->members['Members'][Server::getName()] as $k => $m)
		{
			if($m['host'] == $host)
			{
				unset($this->members['Members'][Server::getName()][$k]);
				$this->saveMembersFile($this->config['File']);
				return TRUE;
			}
		}
		
		return FALSE;
	}
	
	public function getMember($host)
	{
		foreach($this->members['Members'][Server::getName()] as $m)
		{
			if($m['host'] == $host)
				return $m;
		}
		
		return FALSE;
	}
	
	public function getMemberName($host)
	{
		foreach($this->members['Members'][Server::getName()] as $k => $m)
		{
			if($m['host'] == $host)
				return $k;
		}
		
		return FALSE;
	}
	
	public function getMembers()
	{
		$users = array();
		foreach($this->members['Members'][Server::getName()] as $u)
			$users[] = $u['nick'];
		
		return $users;
	}
	
	public function getMembersUsers($group = NULL)
	{
		$users = array();
		
		if(!isset($this->members['Members'][Server::getName()]))
			$this->members['Members'][Server::getName()] = array();
		
		foreach($this->members['Members'][Server::getName()] as $u)
		{
			if($group == NULL || $u['group'] == $group)
				$users[] = $u['host'];
		}
		
		return $users;
	}
	
	public function loadMembersFile($file)
	{
		if(!is_file($file))
			touch($file);
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
