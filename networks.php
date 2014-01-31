<?php
require_once("melanobot.php");

$network_quakenet = array(
	new MelanoBotServer('euroserv.fr.quakenet.org',6667),
	new MelanoBotServer('portlane.se.quakenet.org',6667),
	new MelanoBotServer('xs4all.nl.quakenet.org',6667),
	new MelanoBotServer('underworld1.no.quakenet.org',6667),
	new MelanoBotServer('port80a.se.quakenet.org',6667),
	new MelanoBotServer('jubii2.dk.quakenet.org',6667),
	new MelanoBotServer('blacklotus.ca.us.quakenet.org',6667),
	new MelanoBotServer('servercentral.il.us.quakenet.org',6667),
	new MelanoBotServer('irc.quakenet.org',6667),
);

$network_freenode = array(
	new MelanoBotServer('chat.freenode.net',6665),
	new MelanoBotServer('chat.freenode.net',7000),
);

$network_ponychat = array(
	new MelanoBotServer('irc.ponychat.net',6667),
	new MelanoBotServer('irc.eu.ponychat.net',6667),
	new MelanoBotServer('irc.us.ponychat.net',6667),
	new MelanoBotServer('irc.jp.ponychat.net',6667),
);