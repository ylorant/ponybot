<?php

class Plugins extends Events
{
	
	private $_main; ///<  Reference to main class
	private $_plugins; ///< Plugins list
	private $_pluginClasses; ///< Plugin classes names
	private $_pluginCache = array();
	private $_routines = array();
	private $_regex = array();
	
	/** Constructor for PluginManager
	 * Initializes the class.
	 * 
	 * \param $main A reference to the main program class (Ponybot class).
	 */
	public function __construct(&$main)
	{
		$this->_main = $main;
		$this->_plugins = array();
		$this->_defaultLevel = 0;
		$this->_quietReply = FALSE;
		
		//Creating default event handlers
		$this->addEventListener('command', 'Command');
		$this->addEventListener('server', 'Server');
		
		$coreEvents = new CoreEvents($this, $main);
	}
	
	/** Loads plugins from an array.
	 * This function loads plugins from an array, allowing to load multiple plugins in one time. Basically, it justs do a burst call of loadPlugin for all specified
	 * plugins, plus a load verification.
	 * 
	 * \param $list Plugin list to be loaded.
	 * \return TRUE if all plugins loaded successfully, FALSE otherwise.
	 */
	public function loadPlugins($list, $manual = FALSE)
	{
		if(!is_array($list))
			return FALSE;
		
		Ponybot::message('Loading plugins : $0', array(join(', ', $list)));
		$loadedPlugins = array();
		foreach($list as $plugin)
		{
			if(!in_array($plugin, $this->getLoadedPlugins()))
			{
				$return = $this->loadPlugin($plugin, $manual);
				if($return !== FALSE)
					$loadedPlugins[] = $plugin;
				else
					return FALSE;
			}
		}
		
		return TRUE;
	}
	
	/** Returns the name of a plugin from its instance.
	 * This function returns the name of a plugin, from its instance.
	 * 
	 * \param $object A plugin instance.
	 * 
	 * \return The plugins' name or NULL if the plugin class is not found.
	 */
	public function getName($object)
	{
		if(is_object($object))
		{
			foreach($this->_plugins as $plugin)
			{
				if($plugin['obj'] == $object)
					return $plugin['name'];
			}
		}
		elseif(is_string($object))
		{
			foreach($this->_plugins as $plugin)
			{
				if($plugin['className'] == $object)
					return $plugin['name'];
			}
		}
		
		return NULL;
	}
	
	public function getPlugin($name)
	{
		if(isset($this->_plugins[$name]))
			return $this->_plugins[$name]['obj'];
		else
			return false;
	}
	
	public function loadPlugin($plugin, $manual = FALSE)
	{
		Ponybot::message('Loading plugin $0', array($plugin));
		
		//We check that the plugin is not already loaded
		if(in_array($plugin, $this->getLoadedPlugins()))
		{
			Ponybot::message('Plugin $0 is already loaded', array($plugin), E_WARNING);
			return FALSE;
		}
		
		if(!is_file('plugins/'.$plugin.'.php'))
		{
			Ponybot::message('Plugin $0 does not exists (File : $1)', array($plugin, getcwd().'/plugins/'.$plugin.'.php'), E_WARNING);
			return FALSE;
		}
		
		
		if(!isset($this->_pluginCache[$plugin]))
			include('plugins/'.$plugin.'.php'); //If the plugin has not already been loaded, we include the class
		
		return $this->initPlugin($this->_pluginCache[$plugin], $manual); //Else we reload the plugin with the cached data from the first loading
	}
	
	/** Adds plugin data to the bot's cache.
	 * This function adds plugin's data to the bot's cache, to allow it to reload the plugin multiple times.
	 * 
	 * \param $plugin Plugin's name.
	 * \param $data The data to store.
	 * 
	 * \return TRUE if the data stored correctly, FALSE otherwise.
	 */
	public function addPluginData($data)
	{
		if(isset($data['name']))
			$this->_pluginCache[$data['name']] = $data;
		else
		{
			Ponybot::message('Incomplete plugin data : $0', array(Ponybot::dumpArray($data)), E_WARNING);
			return FALSE;
		}
		
		return TRUE;
	}
	
	/** Unloads a plugin.
	 * This function unloads a plugin. It does not unload the dependencies with it yet.
	 * 
	 * \param $plugin The plugin to unload.
	 * 
	 * \return TRUE if the plugin successuly unloaded, FALSE otherwise.
	 */
	public function unloadPlugin($plugin)
	{
		Ponybot::message('Unloading plugin $0', array($plugin));
		
		//We check that the plugin is not already loaded
		if(!in_array($plugin, $this->getLoadedPlugins()))
		{
			Ponybot::message('Plugin $0 is not loaded', array($plugin), E_WARNING);
			return FALSE;
		}
		
		//Searching plugins that depends on the one we want to unload
		foreach($this->_plugins as $pluginName => &$pluginData)
		{
			if(in_array($plugin, $pluginData['dependencies']))
			{
				Ponybot::message('Plugin $0 depends on plugin $1. Cannot unload plugin $1.', array($pluginName, $plugin), E_WARNING);
				return FALSE;
			}
		}
		
		//Deleting routines
		if(isset($this->_routines[$this->_plugins[$plugin]['className']]))
		{
			foreach($this->_routines[$this->_plugins[$plugin]['className']] as $eventName => $event)
				$this->deleteRoutine($this->_plugins[$plugin]['obj'], $eventName);
		}
		
		//Deleting server events
		foreach($this->_serverEvents as $eventName => $eventList)
		{
			if(isset($eventList[$this->_plugins[$plugin]['className']]))
				$this->deleteServerEvent($eventName, $this->_plugins[$plugin]['obj']);
		}
		
		//Deleting commands
		foreach($this->_commands as $eventName => $eventList)
		{
			if(isset($eventList[$this->_plugins[$plugin]['className']]))
				$this->deleteCommand($eventName, $this->_plugins[$plugin]['obj']);
		}
		
		$dependencies = $this->_plugins[$plugin]['dependencies'];
		
		$this->_plugins[$plugin]['obj']->destroy();
		unset($this->_plugins[$plugin]['obj']);
		unset($this->_plugins[$plugin]);
		
		//Cleaning automatically loaded dependencies
		foreach($dependencies as $dep)
		{
			if($this->_plugins[$dep]['manual'] == FALSE)
			{
				Ponybot::message('Unloading automatically loaded dependency $0.', array($dep));
				$this->unloadPlugin($dep);
			}
		}
		
		Ponybot::message('Plugin $0 unloaded successfully', array($plugin));
		
		return TRUE;
	}
	
	/** Initializes a plugin.
	 * This function is called from the plugin file, after the plugin class definition. It loads the plugin from the data given in argument, includes dependencies,
	 * loads the class, automatically binds the events to correctly named methods.
	 * 
	 * \param $params Loading parameters for the plugin. This associative array allows these values :
	 * 			\li \b name : The name to be used for the plugin (i.e. the file name). String.
	 * 			\li \b className : The main class to be instancied for the plugin. String.
	 * 			\li \b dependencies : The dependencies the plugin needs for functionning. Array.
	 * 			\li \b autoload : To let know if the function has also to do automatic binding of events. Boolean.
	 * \param $manual Manual loading indicator. It permits the bot to know if a plugin has been loaded manually or automatically, by dependence.
	 * \return TRUE if plugin initialized successfully, FALSE otherwise (and throws many warnings).
	 */
	public function initPlugin($params, $manual)
	{
		//Checking that we have everything needed to load the plugin
		if(!is_array($params) || !isset($params['name']) || !isset($params['className']))
		{
			Ponybot::message('Cannot load plugin with given data : $0', array(Ponybot::dumpArray($params)), E_WARNING);
			return FALSE;
		}
		
		//Load dependencies if necessary
		if(!empty($params['dependencies']) && is_array($params['dependencies']))
		{
			Ponybot::message('Loading plugin dependencies for $0.', array($params['name'])	);
			$ret = $this->loadPlugins($params['dependencies']);
			if(!$ret)
			{
				Ponybot::message('Cannot load plugin dependencies, loading aborted.', array(), E_WARNING);
				return FALSE;
			}
			Ponybot::message('Loaded plugin dependencies for $0.', array($params['name']));
		}
		elseif(!empty($params['dependencies']))
			Ponybot::message('Dependencies list is not an array.', array(), E_WARNING);
		
		//Init of plugin data array, and plugin instanciation
		$this->_plugins[$params['name']] = array(
		'obj' => NULL,
		'name' => $params['name'],
		'display' => (isset($params['display']) ? $params['display'] : $params['name']),
		'dependencies' => (isset($params['dependencies']) ? $params['dependencies'] : array()),
		'className' => $params['className'],
		'manual' => $manual);
		$this->_plugins[$params['name']]['obj'] = new $params['className']($this, $this->_main);
		
		//Autoloading !!1!
		if(isset($params['autoload']) && $params['autoload'])
		{
			Ponybot::message('Using automatic events recognition...');
			$methods = get_class_methods($params['className']); //Get all class methods for plugin
			
			//Analyse all class methods
			foreach($methods as $method)
			{
				//Checks for routines
				if(preg_match('#^Routine#', $method))
					$this->addRoutine($this->_plugins[$params['name']]['obj'], $method);
					
				//Checks for plugin-defined events
				foreach($this->_autoMethods as $listener => $prefix)
				{
					if(preg_match('#^'.$prefix.'#', $method))
					{
						$event = strtolower(preg_replace('#'.$prefix.'(.+)#', '$1', $method));
						Ponybot::message('Binding method $0::$1 on event $2/$3', array($params['className'], $method, $listener, $event), E_DEBUG);
						$this->addEvent($listener, $params['className'], $event, array($this->_plugins[$params['name']]['obj'], $method));
					}
				}
			}
		}
		
		$ret = $this->_plugins[$params['name']]['obj']->init(); //Call to init() method of loaded plugin, for internal initializations and such.
		
		if($ret === FALSE)
		{
			Ponybot::message('Could not load plugin $0', array($params['name']));
			unset($this->plugins[$params['name']]);
			return FALSE;
		}
		
		Ponybot::message('Loaded plugin $0', array($params['name']));
		
		//Now that the plugin is loaded, we update the list of all plugins' classes names
		$this->_reloadPluginsClasses();
		
		return TRUE;
	}
	
	/** Reloads the plugins' classes cache.
	 * This function reloads the cache containing the classes loaded for each plugin, for better performances while executing an event.
	 * 
	 * \return Nothing.
	 */
	private function _reloadPluginsClasses()
	{
		$this->_pluginClasses = array();
		foreach($this->_plugins as $plugin)
			$this->_pluginClasses[$plugin['className']] = $plugin['name'];
	}
	
	/** Gets the currently loaded plugins.
	 * This function returns an array containing the list of all currently loaded plugins' names.
	 * 
	 * \return The currently loaded plugins' names, in an array.
	 */
	public function getLoadedPlugins()
	{
		return array_keys($this->_plugins);
	}
	
	/** Adds a routine to the event manager.
	 * This function adds a routine to the event manager, i.e. a function that will be executed every once in a while.
	 * 
	 * \param $plugin A reference to the plugin's class where the method is.
	 * \param $method The name of the method to be executed.
	 * \param $time The time interval between 2 executions of the routine. Defaults to 1 second.
	 * 
	 * \return TRUE if method registered correctly, FALSE otherwise.
	 */
	public function addRoutine(&$plugin, $method, $time = 1)
	{
		Ponybot::message('Adding routine $0, executed every $1s', array(get_class($plugin).'::'.$method, $time), E_DEBUG);
		
		if(!method_exists($plugin, $method)) //Check if method exists
		{
			Ponybot::message('Error : Target method does not exists.', array(), E_DEBUG);
			return FALSE;
		}
		
		$this->_routines[get_class($plugin)][$method] = array($plugin, $time, array());
		
		return TRUE;
	}
	
	/** Deletes a routine.
	 * This function deletes a routine from the event manager.
	 * 
	 * \param $plugin A reference to the plugin's class where the method is.
	 * \param $method The name of the method to be deleted.
	 * 
	 * \return TRUE if method deleted correctly, FALSE otherwise.
	 */
	public function deleteRoutine(&$plugin, $method)
	{
		Ponybot::message('Deleting routine $0', array(get_class($plugin).'::'.$method), E_DEBUG);
		
		if(!isset($this->_routines[get_class($plugin)]))
		{
			Ponybot::message('Plugin $0 does not exists in routine list.', array($event), E_DEBUG);
			return FALSE;
		}
		
		if(!isset($this->_routines[get_class($plugin)][$method]))
		{
			Ponybot::message('Routine does not exists.', array(), E_WARNING);
			return FALSE;
		}
		
		unset($this->_routines[get_class($plugin)][$method]);
		
		return TRUE;
	}
	
	/** This function allow the plugin to changes the time interval of one of his routines. It is useful when using automatic revent detection, because it does not
	 * handles custom timers for routines (they are set to 1 second).
	 * 
	 * \param $plugin A reference to the plugin's class where the method is.
	 * \param $method The name of the method to be updated.
	 * \param $time The new time interval.
	 * 
	 * \return TRUE if method modified correctly, FALSE otherwise.
	 */
	public function changeRoutineTimeInterval(&$plugin, $method, $time)
	{
		Ponybot::message('Changing routine $0 time interval to $1s', array(get_class($plugin).'::'.$method, $time), E_DEBUG);
		
		if(!isset($this->_routines[get_class($plugin)]))
		{
			Ponybot::message('Plugin $0 does not exists in routine list.', array(get_class($plugin)), E_DEBUG);
			return FALSE;
		}
		
		if(!isset($this->_routines[get_class($plugin)][$method]))
		{
			Ponybot::message('Routine does not exists.', array(), E_DEBUG);
			return FALSE;
		}
		
		$this->_routines[get_class($plugin)][$method][1] = $time;
		
		return TRUE;
	}
	
	/** Executes all the routines for all plugins.
	 * This function executes all the routines for all plugins, whether checking if their interval timed out, or not (so all routines are executed), depending
	 * on the value of the \b $force param.
	 * 
	 * \param $force Forces the routines to be executed or not. By default it does not executes them.
	 * 
	 * \return TRUE if routines executed correctly, FALSE otherwise.
	 */
	public function callAllRoutines($force = FALSE)
	{
		$serverName = Server::getName();
		foreach($this->_routines as $className => &$class)
		{
			foreach($class as $name => &$routine)
			{
				if($force || !isset($routine[2][$serverName]) || (time() >= $routine[2][$serverName] + $routine[1] /*&& time() != $routine[2][$serverName] */))
				{
					$routine[0]->$name();
					$routine[2][$serverName] = time();
				}
			}
		}
	}
	
	public function addRegexEvent($regex, $callback)
	{
		$this->_regex[] = array($regex, $callback);
	}
	
	public function execRegexEvents($cmd, $string)
	{
		foreach($this->_regex as $el)
		{
			if(preg_match('#^'.$el[0].'$#isU', $string, $matches))
			{
				call_user_func_array($el[1], array($cmd, $matches));
				break;
			}
		}
	}
}

class Plugin
{
	protected $_main; ///< Reference to main class (Leelabot)
	protected $_plugins; ///< Reference to plugin manager (PluginManager)
	protected $config; ///< Plugin configuration
	
	public function __construct(&$plugins, &$main)
	{
		$this->_plugins = $plugins;
		$this->_main = $main;
		
		$plugin = ucfirst($plugins->getName(get_class($this)));
		
		if(!$main->config->getConfig('Plugin.'.$plugin))
				$main->config->setConfig('Plugin.'.$plugin, array());
		
		$this->config = $main->config->getConfig('Plugin.'.$plugin);
	}
	
	/** Default plugin init function.
	 * This function is empty. Its unique purpose is to avoid using method_exists() on PluginManager::initPlugin(). Returns TRUE.
	 * 
	 * \return TRUE.
	 */
	public function init()
	{
		return TRUE;
	}
	
	/** Default plugin destroy function.
	 * This function is empty. Its unique purpose is to avoid using method_exists() on PluginManager::unloadPlugin(). Returns TRUE.
	 * 
	 * \return TRUE.
	 */
	public function destroy()
	{
		return TRUE;
	}
	
	public function addRegexEvent($regex, $callback)
	{
		$this->_plugins->addRegexEvent($regex, $callback);
	}
	
	public function execRegexEvents($cmd, $string)
	{
		$this->_plugins->execRegexEvents($cmd, $string);
	}
}
