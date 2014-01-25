<?php

require_once("melanobot.php");

abstract class ExecutorBase
{
	public $auth;
	
	/// Check that the user can run the executor
	function check_auth($nick,$host,BotDriver $driver)
	{
		return !$this->auth || $driver->user_in_list($this->auth,$nick,$host);
	}
	
	/// Show help about this command
	abstract function help(MelanoBot $bot,$channel);
	
	/// Check that with the data provided by cmd, this executor can run
	abstract function check(MelanoBotCommand $cmd,MelanoBot $bot,BotDriver $driver);
	
	/// Run this executor
	abstract function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotDriver $driver);
	
	abstract function name();
	
	abstract function install_on(BotDriver $driver);
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
	
	function CommandExecutor($name,$auth,$synopsis="",$description="",$irc_cmd='PRIVMSG')
	{
		$this->name = $name;
		$this->auth = $auth;
		$this->synopsis = $synopsis;
		$this->description = $description;
		$this->reports_error = false;
		$this->irc_cmd = $irc_cmd;
	}
	
	function check(MelanoBotCommand $cmd,MelanoBot $bot,BotDriver $driver)
	{
		return $this->check_auth($cmd->from,$cmd->host,$driver);
	}
	
	/// Show help about this command
	function help(MelanoBot $bot,$channel)
	{
		$bot->say($channel,"\x0304".$this->name()."\x03: \x0314{$this->synopsis}\x03");
		$bot->say($channel,"\x0302{$this->description}\x03");
	}
	
	
	function name()
	{
		return $this->name;
	}
	
	function install_on(BotDriver $driver)
	{
		$driver->add_executor($this);
	}
}

abstract class RawCommandExecutor extends ExecutorBase
{
	
	function check(MelanoBotCommand $cmd,MelanoBot $bot,BotDriver $driver)
	{
		return $cmd->cmd == null;
	}
	
	/// Show help about this command
	function help(MelanoBot $bot,$channel)
	{
	}
	
	function name()
	{
		return null;
	}
	
	function install_on(BotDriver $driver)
	{
		$driver->add_raw_executor($this);
	}
}

abstract class Filter extends ExecutorBase
{
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotDriver $driver)
	{
		return $this->check($cmd,$bot,$driver);
	}
	
	
	/// Show help about this filter
	function help(MelanoBot $bot,$channel)
	{
	}
	
	function name()
	{
		return null;
	}
	
	function install_on(BotDriver $driver)
	{
		$driver->add_filter($this);
	}
}

/// Runs at the end
abstract class PostExecutor
{
	
	abstract function execute( BotDriver $driver);
	
	function install_on(BotDriver $driver)
	{
		$driver->add_post_executor($this);
	}
}
/// Runs at the beginning
abstract class PreExecutor
{
	
	abstract function execute( BotDriver $driver);
	
	function install_on(BotDriver $driver)
	{
		$driver->add_pre_executor($this);
	}
}

/**
 * \brief Get and execute commands
 */
class BotDriver
{
	public $executors = array();
	public $raw_executors = array();
	public $filters = array();
	public $post_executors = array();
	public $pre_executors = array();
	public $on_error = null;
	public $lists = array();
	private $grant_access = array();
	public $bot;
	
	function BotDriver(MelanoBot $bot)
	{
		$this->bot = $bot;
		$this->grant_access['admin'] = array('owner');
	}
	
	/// Append an executor to the list
	function add_executor(CommandExecutor $ex)
	{
		if ( $ex->irc_cmd != 'PRIVMSG' )
			$this->add_executor_irc($ex);
		else
			$this->executors [$ex->name]= $ex;
	}
	
	/// Append an executor to the list
	function add_executor_irc(CommandExecutor $ex)
	{
		if ( !isset($this->executors_irc [$ex->irc_cmd]) )
			$this->executors_irc [$ex->irc_cmd] = array();
		$this->executors_irc [$ex->irc_cmd][] = $ex;
	}
	
	/// Append an executor to the list
	function add_raw_executor(RawCommandExecutor $ex)
	{
		$this->raw_executors []= $ex;
	}
	/// Append an executor to the list
	function add_post_executor(PostExecutor $ex)
	{
		$this->post_executors []= $ex;
	}
	/// Append an executor to the list
	function add_pre_executor(PostExecutor $ex)
	{
		$this->pre_executors []= $ex;
	}
	
	/// Append a filter to the list
	function add_filter(Filter $ex)
	{
		$this->filters []= $ex;
	}
	
	function install($executors)
	{
		if ( !is_array($executors) )
			$executors->install_on($this);
		else
			foreach($executors as $ex)
				$ex->install_on($this);
	}
	
	/// Add/update an IRC user to a user list
	function add_to_list($list,$nick,$host=null)
	{
		if ( !isset($this->lists[$list]) )
			$this->lists[$list] = array();
		$this->lists[$list][$nick] = $host;
	}
	
	/**
	 * \brief Remove a user from a list
	 * \return FALSE if the user isn't in the list
	 */
	function remove_from_list($list,$nick)
	{
		if ( isset($this->lists[$list]) && array_key_exists($nick,$this->lists[$list]) ) 
		{
			unset($this->lists[$list][$nick]);
			return true;
		}
		return false;
	}
	
	/**
	 * \brief Check whether a user is in a list
	 */
    function user_in_list($list,$nick,$host)
    {
		if ( isset($this->lists[$list]) )
		{
			foreach ( $this->lists[$list] as $l_nick => $l_host )
			{
				if ( $l_host == null )
				{
					if ( $l_nick == $nick )
						return true;
				}
				else if ( $l_host == $host ) 
					return true;
			}
		}
		
		if ( isset($this->grant_access[$list]) )
			foreach($this->grant_access[$list] as $l)
				if ( $this->user_in_list($l,$nick,$host) )
					return true;
		return false;
	}
	
	function filter($cmd)
	{
		foreach($this->filters as $f )
		{
			if(!$f->check($cmd,$this->bot,$this))
				return false;
		}
		return true;
		
	}
	
	function loop_step()
	{
		$cmd = $this->bot->loop_step();
		if ( !$this->bot->connected() )
			return false;
			
		if ( $cmd != null )
		{
			$this->bot->log(print_r($cmd,true),3);
			if ( $this->filter($cmd) )
			{
				if ( $cmd->irc_cmd == "PRIVMSG" )
				{
					if ( isset($this->executors[$cmd->cmd]) )
					{
						$ex = $this->executors[$cmd->cmd];
						if ( $ex->reports_error || $ex->check($cmd,$this->bot,$this) )
							$ex->execute($cmd,$this->bot,$this);
						elseif ( $this->on_error )
						{
							$on_error = $this->on_error;
							$on_error($cmd,$this->bot,$this);
						}
					}
					else
					{
						foreach($this->raw_executors as $ex)
							if ( $ex->check($cmd,$this->bot,$this) )
							{
								$ex->execute($cmd,$this->bot,$this);
								break;
							}
					}
				}
				elseif ( isset($this->executors_irc[$cmd->irc_cmd]) )
				{
					foreach ( $this->executors_irc[$cmd->irc_cmd] as $ex )
						if ( $ex->check($cmd,$this->bot,$this) )
							$ex->execute($cmd,$this->bot,$this);
				}
			}
		}
		
		return true;
	}
	
	function run()
	{
		if ( !$this->bot )
			return;
		
		foreach ( $this->pre_executors as $ex )
			$ex->execute($this);
			
		$this->bot->login();
		
		while($this->loop_step());
		
		foreach ( $this->post_executors as $ex )
			$ex->execute($this);
	}
}