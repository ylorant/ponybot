<?php

class PluginFileIFace extends Plugin
{
	private $_in;
	private $_out;
	
	public function init()
	{
		if(!isset($this->config['Root']))
		{
			Ponybot::message('FileIFace: Undefined root directory.', array(), E_ERROR);
			return FALSE;
		}
		
		if(!is_dir($this->config['Root']))
			mkdir($this->config['Root']);
		
		if(!isset($this->config['Infile']) || !isset($this->config['Outfile']))
		{
			Ponybot::message('FileIFace: Undefined in/out file(s).', array(), E_ERROR);
			return FALSE;
		}
		
		$this->_in = fopen($this->config['Root'].'/'.$this->config['Infile'], "w+");
		$this->_out = fopen($this->config['Root'].'/'.$this->config['Outfile'], "w+");
	}
	
	public function RoutineCheckFiles()
	{
		$buffer = '';
		$read = NULL;
		while($read === NULL || $read)
		{
			$read = fread($this->_in, 1024);
			$buffer .= $read;
			usleep(500);
		}
		echo $buffer;
			
		if($buffer)
		{
			$buffer = explode("\n", $buffer);
			foreach($buffer as $data)
			{
				if($data)
				{
					Ponybot::message('Catched data from File interface : $0', array($data));
					$data = explode('->', $data, 2);
					
					if(isset($data[1]))
					{
						$data[0] = trim($data[0]);
						$data[1] = trim($data[1]);
						$irc = ServerList::get($data[0]);
						if(!$irc)
							fputs($this->_out, 'ponybot.error -> 404 Server '.$data[0].' does not exists.'."\r\n");
						else
						{
							$irc = $irc->getIRC();
							$irc->send($data[1]);
						}
					}
				}
			}
			
			fseek($this->_in, 0);
			ftruncate($this->_in, 0);
		}
	}
	
	public function ServerPrivmsg($command)
	{
		fputs($this->_out, Server::getName().' -> '.$command['raw']."\r\n");
	}
}

$this->addPluginData(array(
'name' => 'fileiface',
'className' => 'PluginFileIFace',
'display' => 'IRC-Unix interface plugin',
'dependencies' => array(),
'autoload' => TRUE));
