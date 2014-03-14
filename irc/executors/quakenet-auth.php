<?php
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
				$bot->say("Q","whois $nick");
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
		$this->send_whois($cmd->from,$bot,$data);
	}
}

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
}

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
		if ( preg_match("{-Information for user ([^ ]+) \(using account ([^ )]+)\):}",$cmd->params[0],$matches) )
		{
			$this->get_whois($matches[1], $matches[2], $bot, $data);
		}
	}
}