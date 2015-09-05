<?php

class IRCConnection
{
	private $_socket;
	private $_channels = array();
	private $_users = array();
	private $_buffer = array();
	private $_lastTimed = 0;
	private $_lastSend = 0;
	private $_data;
	private $_floodLimit = false;
	private $_lastCommand = 0;
	
	public function connect($addr, $port)
	{
		$this->_socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		socket_connect($this->_socket, $addr, $port);
		socket_set_nonblock($this->_socket);
		$this->_lastSend = 0;
	}
	
	public function setFloodLimit($value)
	{
		$this->_floodLimit = $value == true;
	}
	
	public function disconnect()
	{
		fclose($this->_socket);
	}
	
	public function read()
	{
		$data = socket_read($this->_socket, 1024);
		
		if(substr($data, -2) == "\r\n")
		{
			$commands = explode("\r\n", $this->_data.$data);
			if(empty($commands[count($commands)-1]))
				array_pop($commands);
			
			$this->_data = "";
			
			return $commands;
		}
		else
			$this->_data .= $data;
		
		return array();
	}
	
	public function joinChannels($channels)
	{
		$chans = $channels;
		if(is_array($channels))
			$chans = join(',', $channels);
		else
			$channels = explode(',', str_replace(' ', '', $channels));
		
		$this->_channels = array_merge($this->_channels, $channels);
		
		str_replace(' ', '', $chans);
		$this->send('JOIN '.$chans);
	}
	
	public function joinChannel($chan)
	{
		$this->_channels = array_merge($this->_channels, array($chan));
		$this->send('JOIN '.$chan);
	}
	
	public function setNick($nick, $user)
	{
		$this->send('NICK '.$nick);
		$this->send('USER '.$user.' '.$user.' '.$user.' '.$user);
	}
	
	public function setPassword($password)
	{
		$this->send('PASS '. $password);
	}
	
	public function message($to, $message)
	{
		$this->send('PRIVMSG '.$to.' :'.$message);
	}
	
	public function timedMessage($to, $message, $time)
	{
		$this->send('PRIVMSG '.$to.' :'.$message, $time);
	}
	
	public function notice($to, $message)
	{
		$this->send('NOTICE '.$to.' :'.$message);
	}
	
	public function kick($from, $who, $reason = '')
	{
		$this->send('KICK '.$from.' '.$who.' :'.$reason);
	}
	
	public function action($channel, $message)
	{
		$this->send("PRIVMSG $channel :ACTION $message");
	}
	
	public function ping($hostname = NULL)
	{
		$hostname = $hostname ? $hostname : gethostname();
		$this->send("PING :". $hostname);
	}
	
	public function getChannels()
	{
		return array_keys($this->_channels);
	}
	
	public function getChannelUsers($channel)
	{
		if(!isset($this->_users[$channel]))
		{
			$this->send("NAMES $channel");
			$cmd = array();
			do
			{
				do
				{
					$data = $this->read();
					$this->processBuffer();
 				} while(!$data);

				Server::step($data);
					
				foreach($data as $msg)
				{
					if($command['command'] == 366 || $command['command'] == 421)
						break;
				}
				
			} while(1);
		}
		
		return array_keys($this->_users[$channel]);
	}
	
	public function whois($nick)
	{
		$this->send("WHOIS $nick");
		do
		{
			do
			{
				$data = $this->read();
				$this->processBuffer();
			} while(!$data);
			
			Server::step($data);
			
			$info = array();
			foreach($data as $msg)
			{
				$cmd = $this->parseMsg($msg);
				switch($cmd['command'])
				{
					case 401:
						return FALSE;
						break;
						
					case 311:
						$info['nick'] = $cmd['additionnal'][0];
						$info['user'] = $cmd['additionnal'][1];
						$info['host'] = $cmd['additionnal'][2];
						break;
					case 307:
						// if($cmd['message'] == 'is a registered nick'
						// 	|| $cmd['message'] == 'is identified for this nick')
							$info['auth'] = $info['nick'];
						break;
					case 319:
						$info['channels'] = explode(' ', $cmd['message']);
						foreach($info['channels'] as $k => $v)
						{
							if(in_array($v[0], array('+', '@')))
								$info['channels'][substr($v, 1)] = str_replace(array('+', '@'), array('v', 'o'), $v[0]);
							else
								$info['channels'][$v] = '';
							unset($info['channels'][$k]);
						}
						break;
					case 318:
						break 2;
				}
			}
		} while(1);
		
		return $info;
	}
	
	public function getChannelRights($channel)
	{
		if(!isset($this->_users[$channel]))
			$this->getChannelUsers($channel);
		
		$return = array('users' => array(), 'voice' => array(), 'operator' => array());
		foreach($this->_users[$channel] as $user => $level)
		{
			switch($level)
			{
				case 'v':
					$return['voice'][] = $user;
					break;
				case 'o':
					$return['operator'][] = $user;
					break;
				default:
					$return['users'][] = $user;
					break;
			}
		}
		
		return $return;
	}
	
	public function userMode($user, $mode, $channel = 'all')
	{
		Ponybot::message("Some user mode change");
		if($channel == 'all')
		{
			foreach($this->_channels as $chan)
			{
				if(in_array($user, $this->getChannelUsers($chan)))
					$this->send('MODE '.$chan.' '.$mode.' '.$user);
			}
		}
		else
			$this->send('MODE '.$channel.' '.$mode.' '.$user);
	}
	
	public function giveVoice($user, $channel = 'all')
	{
		$this->userMode($user, '+v', $channel);
	}
	
	public function takeVoice($user, $channel = 'all')
	{
		$this->userMode($user, '-v', $channel);
	}
	
	public function giveOp($user, $channel = 'all')
	{
		$this->userMode($user, '+o', $channel);
	}
	
	public function takeOp($user, $channel = 'all')
	{
		$this->userMode($user, '-o', $channel);
	}
	
	public function setChannelUsers($channel, $users)
	{
		$list = array();
		foreach($users as $user)
		{
			$nick = substr($user, 1);
			switch($user[0])
			{
				case '@':
					$list[$nick] = 'o';
					break;
				case '+':
					$list[$nick] = 'v';
					break;
				default:
					$list[$user] = '';
			}
		}
		
		$this->_users[$channel] = $list;
	}
	
	public function waitPing()
	{
		$continue = true;
		Ponybot::message("Waiting ping from server...");
		while($continue)
		{
			$this->processBuffer();
			$buffer = $this->read();
			foreach($buffer as $cmd)
			{
				echo '['.Server::getName().'] '.$cmd."\n";
				$cmd = explode(':', $cmd);
				if(trim($cmd[0]) == 'PING')
				{
					$this->send('PONG :'.$cmd[1]);
					$continue = FALSE;
				}
			}
			usleep(5000);
		}
	}
	
	public function userModeAdd($channel, $user, $level)
	{
		$this->_users[$channel][$user] .= $level;
	}
	
	public function userModeRemove($channel, $user, $level)
	{
		$this->_users[$channel][$user] = str_replace($level, '', $this->_users[$channel][$user]);
	}
	
	public function userJoin($channel, $user)
	{
		if(!in_array($user, $this->getChannelUsers($channel)))
			$this->_users[$channel][$user] = '';
	}
	
	public function userPart($channel, $user)
	{
		if(in_array($user, $this->getChannelUsers($channel)))
			unset($this->_users[$channel][$user]);
	}
	
	public function send($data, $time = FALSE)
	{
		if(!$time)
			$this->_buffer[] = $data;
		else
			$this->_buffer['time:'.$time] = $data;
	}
	
	public function processBuffer()
	{
		foreach($this->_buffer as $time => $data)
		{
			if(substr($time, 0, 5) == 'time:')
			{
				if(substr($time, 5) == time())
				{
					echo '->['.Server::getName().'] '.$data."\n";
					socket_write($this->_socket, $data."\r\n");
					unset($this->_buffer[$time]);
				}
			}
			elseif($this->_lastSend + 2 <= time() || !$this->_floodLimit)
			{
				echo '->['.Server::getName().'] '.$data."\n";
				socket_write($this->_socket, $data."\r\n");
				unset($this->_buffer[$time]);
				$this->_lastSend = time();
			}
		}
	}
	
	public function emptyBufferMessages($channel)
	{
		foreach($this->_buffer as $k => $v)
		{
			Ponybot::message($v);
			$v = explode(' ', $v);
			if(($v[0] == 'PRIVMSG' || $v[0] == 'NOTICE') && isset($v[1]) && $v[1] == $channel)
				unset($this->_buffer[$k]);
		}
	}
	
	public function getLastBufferTime()
	{
		$last = '';
		$b = array_keys($this->_buffer);
		do
		{
			$last = array_pop($b);
		} while(substr($last, 0, 4) != 'time');
		
		return substr($last, 5);
	}
	
	public function parseMsg($message)
	{
		$raw = $message;
		$msg = '';
		$command = explode(':', trim($message), 3);
		if(trim($command[0]) == 'PING')
			return array('command' => 'PING', 'additionnal' => $command[1]);
		$message = '';
		if(isset($command[2]))
			$message = $command[2];
		$cmd = explode(' ', $command[1], 4);
		$user = explode('!', $cmd[0]);
		if(isset($user[1]))
		{
			$nick = $user[0];
			$user = $user[1];
		}
		else
			$nick = $user = $user[0];
		if(isset($command[2]))
			$msg = $command[2];
		$command = $cmd[1];
		if(isset($cmd[2]))
			$channel = $cmd[2];
		if(isset($cmd[3]))
			$additionnal_parameters = explode(' ', $cmd[3]);
		else
			$additionnal_parameters = array();
		$return = array('nick' => $nick, 'user' => $user, 'command' => $command, 'channel' => $channel, 'additionnal' => $additionnal_parameters, 'message' => $msg, 'raw' => $raw);
		return $return;
	}
}
