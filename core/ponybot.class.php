<?php

class Ponybot
{
	public $config;
	public $servers;
	private static $_lastError;
	public static $verbose;
	public $plugins;
	
	public function init()
	{
		Ponybot::$verbose = 1;
		Ponybot::message('Starting ponybot...');
		
		ServerList::setMain($this);
		
		$this->config = new Config('conf');
		$this->config->load();
		
		$this->plugins = new Plugins($this);
		$this->plugins->loadPlugins(explode(',',$this->config->getConfig('General.Plugins')));
		
		$servers = $this->config->getConfig('Servers');
		Ponybot::message('Loading servers...');
		foreach($servers as $name => $server)
		{
			Ponybot::message('Loading server $0', array($name));
			$this->servers[$name] = new ServerInstance($this);
			$this->servers[$name]->load($server);
		}
		
		Ponybot::message('Loaded servers.');
		
	}
	
	public static function parseBool($var)
	{
		if(in_array(strtolower($var), array('1', 'on', 'true', 'yes')))
			return TRUE;
		else
			return FALSE;
	}
	
	public static function message($message, $args = array(), $type = E_NOTICE)
	{
		$verbosity = 1;
		$prefix = "";
		//Getting type string
		switch($type)
		{
			case E_NOTICE:
			case E_USER_NOTICE:
				$prefix = 'Notice';
				break;
			case E_WARNING:
			case E_USER_WARNING:
			$prefix = 'Warning';
				if(PHP_OS == 'Linux') //If we are on Linux, we use colors
					echo "\033[0;33m";
				break;
			case E_ERROR:
			case E_USER_ERROR:
				$prefix = 'Error';
				$force = TRUE;
				$verbosity = 0;
				if(PHP_OS == 'Linux') //If we are on Linux, we use colors (yes, I comment twice)
					echo "\033[0;31m";
				break;
			case E_DEBUG:
				$prefix = 'Debug';
				$verbosity = 2;
				break;
			default:
				$prefix = 'Unknown';
		}
		
		//Parsing message vars
		foreach($args as $id => $value)
			$message = str_replace('$'.$id, $value, $message);
		
		if(in_array($type, array(E_USER_ERROR, E_ERROR, E_WARNING, E_USER_WARNING)))
			Ponybot::$_lastError = $message;
		
		//Put it in log, if is opened
		if(Ponybot::$verbose >= $verbosity)
		{
			echo date("m/d/Y h:i:s A").' -- '.$prefix.' -- '.$message.PHP_EOL;
			if(PHP_OS == 'Linux')
				echo "\033[0m";
		}
	}
	
	public function dumpArray($array)
	{
		if(!is_array($array))
			$array = array(gettype($array) => $array);
		
		$return = array();
		
		foreach($array as $id => $el)
		{
			if(is_array($el))
				$return[] = $id.'=Array';
			elseif(is_object($el))
				$return[] = $id.'='.get_class($el).' object';
			elseif(is_string($el))
				$return[] = $id.'="'.$el.'"';
			elseif(is_bool($el))
				$return[] = $id.'='.($el ? 'TRUE' : 'FALSE');
			else
				$return[] = $id.'='.(is_null($el) ? 'NULL' : $el);
		}
		
		return join(', ', $return);
	}
	
	public function getServerName($object)
	{
		foreach($this->servers as $name => $server)
		{
			if($server == $object)
				return $name;
		}
		
		return NULL;
	}
	
	public function run()
	{
		$this->_run = TRUE;
		
		while($this->_run)
		{
			foreach($this->servers as $name => $server)
			{
				//Setting servers for static inner API
				IRC::setServer($this->servers[$name]);
				Server::setServer($this->servers[$name]);
				
				$this->servers[$name]->step();
				usleep(1000);
			}
			
			$this->plugins->execRoutines();
		}
		
		foreach($this->servers as $name => $server)
			$server->disconnect();
		
		foreach($this->plugins->getLoadedPlugins() as $plugin)
			$this->plugins->unloadPlugin($plugin);
			
		return TRUE;
	}
}
