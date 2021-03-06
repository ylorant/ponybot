<?php

define('E_DEBUG', 32768);
//Change this if you do not live in France
date_default_timezone_set('Europe/Paris');

include('core/api.class.php');
include('core/config.class.php');
include('core/events.class.php');
include('core/irc.class.php');
include('core/coreevents.class.php');
include('core/plugins.class.php');
include('core/server.class.php');
include('core/ponybot.class.php');

$ponybot = new Ponybot();
$ponybot->init();
$ponybot->run();
