<?php
require_once("irc/executors/abstract.php");

class Executor_Q_SendWhois_Join extends CommandExecutor
{
	function __construct()
	{
		parent::__construct(null,null);
		$this->irc_cmd = 'JOIN';
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		if ( $cmd->from != $bot->nick )
			$bot->say("Q","whois {$cmd->from}");
	}
}

class Executor_Q_SendWhois_Names extends CommandExecutor
{
	function __construct()
	{
		parent::__construct(null,null);
		$this->irc_cmd = 353;
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		foreach($cmd->params as $nick)
			$bot->say("Q","whois $nick");
	}
}

class Executor_Q_GetWhois extends CommandExecutor
{
	function __construct()
	{
		parent::__construct(null,null);
		$this->irc_cmd = 'NOTICE';
	}
	function check(MelanoBotCommand $cmd,MelanoBot $bot,BotData $driver)
	{
		return $cmd->from == "Q" && $cmd->host == "CServe.quakenet.org" && !empty($cmd->params);
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		if ( preg_match("{-Information for user ([^ ]+) \(using account ([^ )]+)\):}",$cmd->params[0],$matches) )
		{
			$user = $bot->find_user_by_nick($matches[1]);
			if ( $user )
			{
				$user->name = $matches[2];
				Logger::log("irc","!","Nick \x1b[36m$matches[1]\x1b[0m authed as \x1b[31m$matches[2]\x1b[0m");
			}
		}
	}
}