<?php

class PluginRSS extends Plugin
{
	private $rss;
	
	public function init()
	{
		$this->loadRSSList($this->config['File']);
		$this->_plugins->changeRoutineTimeInterval($this, 'RoutineCheckRSS', 30);
	}
	
	public function RoutineCheckRSS()
	{
		Ponybot::message('Checking RSS...');
		foreach($this->rss as $name => $el)
		{
			if(!$contents = @file_get_contents($el['href']))
				continue;
			
			$news = $this->readRSS($contents, $el['last']);
			if(count($news) > 0)
			{
				$time = 0;
				foreach($news as $n)
				{
					if(isset($this->config['Shorten']) && Ponybot::parseBool($this->config['Shorten']))
					{
						$curl = curl_init("http://koinko.in/yourls-api.php?signature=0f1b869a61&action=shorturl&url=".$n['href']."&format=simple");
						curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
						$shortened = curl_exec($curl);
					}
					else
						$shortened = $n['href'];
					
					foreach($el['channels'] as $channel)
						IRC::message($channel, '['.$name.'] '.$n['title'].' - '.$shortened);
					
					$time = $time < $n['time'] ? $n['time'] : $time;
				}
				$this->rss[$name]['last'] = $time;
				$this->saveRSSList($this->config['File']);
			}
		}
	}
	
	public function getRSSTitle($rss)
	{
		$dom = new DOMDocument();
		$dom->loadXML($rss);
		
		$title = $dom->getElementsByTagName('title');
		return $title->item(0)->nodeValue;
	}
	
	public function readRSS($rss, $last)
	{
		$dom = new DOMDocument();
		$dom->loadXML($rss);
		
		$title = $dom->getElementsByTagName('title');
		$RSStitle = $title->item(0)->nodeValue;
		
		//Récupération de la liste des articles
		$itemList = $dom->getElementsByTagName('entry');
		
		$list = array();
		//Ici j'utilise un for pour parcourir la liste des Nodes, mais bon ça peut être un foreach je crois
		for($i = 0; $i < $itemList->length; $i++)
		{
			//On récupère le noeud courant
			$node = $itemList->item($i);
			//Récupération du titre (comme il n'y a qu'un seul élément title par item, je le prend directement par l'adjonction du item(0) à ma méthode qui liste les nodes "title" dans l'item)
			$title = $node->getElementsByTagName('title')->item(0);
			$link = $node->getElementsByTagName('origLink')->item(0)->nodeValue;
			
			//On ajoute la news à la liste si elle est plus récente
			if(($time = strtotime($node->getElementsByTagName('updated')->item(0)->nodeValue)) > $last)
				$list[] = array('title' => $title->nodeValue, 'href' => $link, 'time' => $time);
		}
		
		return $list;
	}
	
	public function loadRSSList($file)
	{
		if(!is_file($file))
			touch($file);
		$this->rss = Ponybot::parseINIStringRecursive(file_get_contents($file));
		
		foreach($this->rss as &$rss)
			$rss['channels'] = explode(',', $rss['channels']);
	}
	
	public function saveRSSList($file)
	{
		$rsslist = $this->rss;
		foreach($rsslist as &$rss)
			$rss['channels'] = join(',', $rss['channels']);
		file_put_contents($file, Ponybot::generateINIStringRecursive($rsslist));
	}
}

$this->addPluginData(array(
'name' => 'rss',
'className' => 'PluginRSS',
'display' => 'RSS News fetcher for Ponybot',
'dependencies' => array(),
'autoload' => TRUE));
