<?php
/**
 * \file
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
require_once("irc/executors/abstract.php");
abstract class Executor_Whois_base extends CommandExecutor
{
	function send_whois($nick,MelanoBot $bot, BotData $data)
	{
		if ( !isset($data->whois_queue) ) $data->whois_queue = array();
		
		if ( $nick != $bot->nick && $nick != "Q" && ! in_array($nick, $data->whois_queue) )
		{
			$user = $bot->find_user_by_nick($nick);
			if ( empty($user->name) )
			{
				$bot->say("Q","whois $nick",64);
				$data->whois_queue []= $nick;
			}
		}
	}
	
	function get_whois($nick, $name, MelanoBot $bot, BotData $data)
	{
		if ( !isset($data->whois_queue) ) $data->whois_queue = array();
		
		$user = $bot->find_user_by_nick($nick);
		if ( $user )
		{
			$user->name = $name;
			Logger::log("irc","!","Nick \x1b[36m$nick\x1b[0m authed as \x1b[31m$name\x1b[0m");

			$data->whois_queue = array_diff($data->whois_queue,array($nick));
		}
	}
}

class Executor_Q_SendWhois_Join extends Executor_Whois_base
{
	function __construct()
	{
		parent::__construct(null,null);
		$this->irc_cmd = 'JOIN';
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		if ( $cmd->from != $bot->nick )
			$this->send_whois($cmd->from,$bot,$data);
		else
			$bot->say("Q","users {$cmd->channel}",64);
	}
}

/*
sending whois for each user, pretty slow
class Executor_Q_SendWhois_Names extends Executor_Whois_base
{
	function __construct()
	{
		parent::__construct(null,null);
		$this->irc_cmd = 353;
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		if ( !isset($data->whois_queue) ) $data->whois_queue = array();
			
		foreach($cmd->params as $nick)
		{
			$this->send_whois($nick,$bot,$data);
		}
	}
}*/

class Executor_Q_GetWhois extends Executor_Whois_base
{
	function __construct()
	{
		parent::__construct(null,null);
		$this->irc_cmd = 'NOTICE';
	}
	function check(MelanoBotCommand $cmd,MelanoBot $bot,BotData $data)
	{
		return $cmd->from == "Q" && $cmd->host == "CServe.quakenet.org" && !empty($cmd->params);
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		static $nickre = "[-_A-Za-z0-9{}^<>\[\]\\\\`]+";
		// whois
		if ( preg_match("{-Information for user ([^ ]+) \(using account ([^ )]+)\):}",$cmd->params[0],$matches) )
		{
			$this->get_whois($matches[1], $matches[2], $bot, $data);
		}
		// users
		else if ( preg_match("{[@+ ]?($nickre)\s+($nickre)\s*(\+[a-z]+)?\s*\((.*)\)}",$cmd->params[0],$matches) )
		{
			$this->get_whois($matches[1], $matches[2], $bot, $data);
		}
	}
}