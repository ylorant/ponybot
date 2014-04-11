<?php
class PluginHello extends Plugin
{
	public function ServerPrivmsg($msg)
	{
		if(strpos($msg["message"], Server::getNick()) !== FALSE)
		{
			foreach($this->config["Words"] as $word)
			{
				if(strpos(strtolower($msg["message"]), $word) !== FALSE)
				{
					IRC::message($msg["channel"], "Salut, ". $msg["nick"]. " !");
					return;
				}
			}
		}
	}
}

$this->addPluginData(array(
'name' => 'hello',
'className' => 'PluginHello',
'display' => 'IRC automatic hello reply plugin',
'dependencies' => array(),
'autoload' => TRUE));
