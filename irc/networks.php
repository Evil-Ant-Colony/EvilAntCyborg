<?php
/**
 * \file
 * \brief IRC network connection definitions
 *
 * These arrays can be used as the first argument to the MelanoBot constructor
 * \note Far from being extensive
 *
 * \author Mattia Basaglia
 * \copyright Copyright 2013-2014 Mattia Basaglia
 * \section License
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

require_once("irc/melanobot.php");

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