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
	
	public function connect($addr, $port)
	{
		$this->_socket = fsockopen($addr, $port);
		stream_set_blocking($this->_socket, false);
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
		$data = fgets($this->_socket);
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
		if(is_array($channels))
			$channels = join(',', $channels);
		
		str_replace(' ', '', $channels);
		$this->send('JOIN '.$channels);
	}
	
	public function joinChannel($chan)
	{
		$this->send('JOIN '.$chan);
	}
	
	public function setNick($nick, $user)
	{
		$this->send('NICK '.$nick);
		$this->send('USER '.$user.' '.$user.' '.$user.' '.$user);
	}
	
	public function message($to, $message)
	{
		$this->send('PRIVMSG '.$to.' :'.$message);
	}
	
	public function notice($to, $message)
	{
		$this->send('NOTICE '.$to.' :'.$message);
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
				$cmd = explode(':', $cmd);
				if(trim($cmd[0]) == 'PING')
				{
					$this->send('PONG :'.$cmd[1]);
					$continue = FALSE;
				}
			}
			usleep(5000);
		}
		Ponybot::message("Got pong.");
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
				if($this->_lastCommand + substr($time, 5) <= time())
				{
					fputs($this->_socket, $data."\r\n");
					unset($this->_buffer[$time]);
					$this->_lastTimed = time();
				}
			}
			elseif($this->_lastSend + 2 <= time() || !$this->_floodLimit)
			{
				fputs($this->_socket, $data."\r\n");
				unset($this->_buffer[$time]);
			}
		}
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
		$cmd = explode(' ', $command[1]);
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
