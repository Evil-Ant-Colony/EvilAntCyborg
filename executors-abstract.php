<?php

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
	
	function CommandExecutor($name,$auth,$synopsis="",$description="",$irc_cmd='PRIVMSG')
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


/**
 * \brief Send commands to the right executor
 */
class BotCommandDispatcher
{
	public $executors = array();      ///< List of executors for direct PRIVMSG commands
	public $raw_executors = array();  ///< List of executors for indirect PRIVMSG commands
	public $filters = array();        ///< Stuff to be applied to each command before checking for execution
	public $on_error = null;          ///< Function called when a user doesn't have the right to fire a direct executor
	public $channel_filter = array(); ///< List of channels this dispatcher is allowed to work on, empty == all channels
	public $prefix = null;            ///< Customized prefix for this dispatcher, empty == bot default
	
	function BotCommandDispatcher($channel_filter = array(), $prefix = null)
	{
		if ( !is_array($channel_filter) )
			$this->channel_filter = array($channel_filter);
		else
			$this->channel_filter = $channel_filter;
		$this->prefix = $prefix;
	}
	
	function matches_channel($channel)
	{
		return empty($this->channel_filter) || in_array($channel,$this->channel_filter) ;
	}
	
	function id()
	{
		if ( !$this->channel_filter && !$this->prefix )
			return "Global Dispatcher";
		$id = "";
		$chans = implode(" ",$this->channel_filter);
		if ( $chans )
			$id = "($chans)";
		if ( $this->prefix );
			$id = "{$this->prefix} $id";
		return $id;
	}
	
	/// Whether the channel and prefix match this dispatcher
	function matches(MelanoBotCommand $cmd )
	{
		return $this->matches_channel($cmd->channel ) && ( !$this->prefix || 
				( $cmd->cmd == null && count($cmd->params) > 0 && $cmd->params[0] == $this->prefix ) );
	}
	
	// Convert the command (ie remove prefix)
	function convert(MelanoBotCommand $cmd)
	{
		if ( $this->prefix && count($cmd->params) > 0 && $cmd->params[0] == $this->prefix )
		{
			$cmd = new MelanoBotCommand($cmd->cmd, $cmd->params, $cmd->from, 
					$cmd->host, $cmd->channel, $cmd->raw, $cmd->irc_cmd);
			array_shift($cmd->params);
			if ( count($cmd->params) > 0  )
				$cmd->cmd = array_shift($cmd->params);
		}
		return $cmd;
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
	
	function filter(MelanoBotCommand $cmd,MelanoBot $bot,BotData $data)
	{
		foreach($this->filters as $f )
		{
			if(!$f->check($cmd,$bot,$data))
				return false;
		}
		return true;
		
	}
	
	function log($bot,$executor)
	{
		$bot->log("\x1b[34mHandled by \x1b[1m".get_class($executor).
			"\x1b[22m via \x1b[1m".$this->id()."\x1b[0m\n",3);
	}
	
	
	function loop_step(MelanoBotCommand $cmd,MelanoBot $bot,BotData $data)
	{
		if ( !$this->matches($cmd) )
			return false;
		$cmd = $this->convert($cmd);
		$keep_running = true;
		if ( $this->filter($cmd, $bot, $data) )
		{
			if ( $cmd->irc_cmd == "PRIVMSG" )
			{
				if ( isset($this->executors[$cmd->cmd]) )
				{
					$ex = $this->executors[$cmd->cmd];
					if ( $ex->reports_error || $ex->check($cmd,$bot,$data) )
						$ex->execute($cmd,$bot,$data);
					elseif ( $this->on_error )
					{
						$on_error = $this->on_error;
						$on_error($cmd,$bot,$data);
					}
					$keep_running = $ex->keep_running();
					$this->log($bot,$ex);
				}
				else
				{
					foreach($this->raw_executors as $ex)
						if ( $ex->check($cmd,$bot,$data) && $keep_running )
						{
							$ex->execute($cmd,$bot,$data);
							$keep_running = $ex->keep_running();
							$this->log($bot,$ex);
							break;
						}
				}
			}
			elseif ( isset($this->executors_irc[$cmd->irc_cmd]) )
			{
				foreach ( $this->executors_irc[$cmd->irc_cmd] as $ex )
					if ( $ex->check($cmd,$bot,$data) && $keep_running )
					{
						$ex->execute($cmd,$bot,$data);
						$keep_running = $ex->keep_running();
						$this->log($bot,$ex);
					}
			}
		}
		return !$keep_running;
	}
}