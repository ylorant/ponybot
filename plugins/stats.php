<?php

class PluginStats extends Plugin
{
	private $file;
	
	public function init()
	{
		$this->file = $this->config["StatsFile"];
	}

	public function CommandStats($cmd, $data)
	{
		if (!$data[0])
			IRC::message($cmd['channel'], 'I need a name! (usage: !stats [name])');
    	else if (($handle = fopen($this->file, "r")) !== false)
		{
    		while (($fdata = fgetcsv($handle, 1000, ",")) !== false)
    		{
    			if (trim($fdata[0]) == $data[0])
    			{
    				//English sexual orientation
    				if ($fdata[2] || $fdata[3] || $fdata[4])
	    				$orientation = ($fdata[2] == 'x')? 'hetero' : (($fdata[3] == 'x')? 'gay' : 'bi');
	    			else
	    				$orientation = '';

	    			//English sex, he/she and situation
					$sex = $orienation.(($fdata[1] == 'Homme')? 'male' : 'female');
					$he_she = ($fdata[1] == 'Homme')? 'he' : 'she';
					$situation = ($fdata[5])? 'in a relationship' : 'single';

					//If the user said his birthday date (...) or (...) // on the CSV, age is generated from birthday date
					if ($fdata[6])
						IRC::message($cmd['channel'], 'I know this name! let\'s see... '.$data[0].' is a '.$orientation.' '.$sex.'; '.$he_she.' is born the '.$fdata[6].', so '.$he_she.' is '.$fdata[7].' years old; also '.$he_she.' is '.$situation);
					else
						IRC::message($cmd['channel'], 'I know this name! let\'s see... '.$data[0].' is a '.$orientation.' '.$sex.'; '.$he_she.' is '.$situation.' ');

					//MLP-related stats, if the user has them (pony and episode or only pony)
					if ($fdata[9] && $fdata[10] && $fdata[11])
					{
						IRC::message($cmd['channel'], 'Now, the pony stats: '.$data[0].' likes '.$fdata[9].' and prefer S'.$fdata[10].'E'.$fdata[11].' ');
	    				if (str_replace(' ', '', $fdata[9]) == Server::getNick())
							IRC::message($cmd['channel'], 'Oh, '.$he_she.' loves me! thanks '.$data[0].' <3');
					}
					else if ($fdata[9])
					{
						IRC::message($cmd['channel'], 'Now, the pony stats: '.$data[0].' likes '.$fdata[9].'. ');
	    				if (str_replace(' ', '', $fdata[9]) == Server::getNick())
							IRC::message($cmd['channel'], 'Oh, '.$he_she.' loves me! thanks '.$data[0].' <3');

					}
					fclose($handle);
    				return true;
    			}
			}
			fclose($handle);
		}
		if ($data[0])
			IRC::message($cmd['channel'], 'I don\'t know this name, i wish i could help you.');
	}
}

$this->addPluginData(array(
'name' => 'stats',
'className' => 'PluginStats',
'display' => 'User Stats plugin',
'dependencies' => array(),
'autoload' => TRUE));
