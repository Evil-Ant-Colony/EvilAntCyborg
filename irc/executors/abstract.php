<?php
require_once("misc/logger.php");

abstract class ExecutorBase
{
	public $auth;
	
	/// Check that the user can run the executor
	function check_auth($nick,$host,BotData $driver)
	{
		return !$this->auth || $driver->user_in_list($this->auth,$nick,$host);
	}
	
	/// Show help about this command
	abstract function help(MelanoBotCommand $cmd,MelanoBot $bot,BotData $driver);
	
	/// Check that with the data provided by cmd, this executor can run
	abstract function check(MelanoBotCommand $cmd,MelanoBot $bot,BotData $driver);
	
	/// Run this executor
	abstract function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver);
	
	abstract function name();
	
	abstract function install_on(BotCommandDispatcher $driver);
	
	// whether the command may be executed again
	function keep_running() { return false; }
}

/**
 * \brief Executes a command
 */
abstract class CommandExecutor extends ExecutorBase
{
	public $name;
	public $synopsis;
	public $description;
	public $reports_error;
	public $irc_cmd;
	
	function __construct($name,$auth,$synopsis="",$description="",$irc_cmd='PRIVMSG')
	{
		$this->name = $name;
		$this->auth = $auth;
		$this->synopsis = $synopsis;
		$this->description = $description;
		$this->reports_error = false;
		$this->irc_cmd = $irc_cmd;
	}
	
	function check(MelanoBotCommand $cmd,MelanoBot $bot,BotData $driver)
	{
		return $this->check_auth($cmd->from,$cmd->host,$driver);
	}
	
	/// Show help about this command
	function help(MelanoBotCommand $cmd,MelanoBot $bot,BotData $driver)
	{
		$bot->say($cmd->channel,"\x0304".$this->name()."\x03: \x0314{$this->synopsis}\x03");
		$bot->say($cmd->channel,"\x0302{$this->description}\x03");
	}
	
	
	function name()
	{
		return $this->name;
	}
	
	function install_on(BotCommandDispatcher $driver)
	{
		$driver->add_executor($this);
	}
	
	
	function keep_running() { return $this->irc_cmd != 'PRIVMSG'; }
}

abstract class RawCommandExecutor extends ExecutorBase
{
	
	function check(MelanoBotCommand $cmd,MelanoBot $bot,BotData $driver)
	{
		return $cmd->cmd == null;
	}
	
	/// Show help about this command
	function help(MelanoBotCommand $cmd,MelanoBot $bot,BotData $driver)
	{
	}
	
	function name()
	{
		return null;
	}
	
	function install_on(BotCommandDispatcher $driver)
	{
		$driver->add_raw_executor($this);
	}
}

abstract class Filter extends ExecutorBase
{
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		return $this->check($cmd,$bot,$driver);
	}
	
	
	/// Show help about this filter
	function help(MelanoBotCommand $cmd,MelanoBot $bot,BotData $driver)
	{
	}
	
	function name()
	{
		return null;
	}
	
	function install_on(BotCommandDispatcher $driver)
	{
		$driver->add_filter($this);
	}
}

/// Runs at the very beginning or at the very end
abstract class StaticExecutor
{
	
	abstract function execute( MelanoBot $bot, BotData $driver);
}