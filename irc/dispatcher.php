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
	public $executors_irc = array();
	
	function __construct($channel_filter = array(), $prefix = null)
	{
		if ( !is_array($channel_filter) )
			$this->channel_filter = array($channel_filter);
		else
			$this->channel_filter = $channel_filter;
		$this->prefix = $prefix;
	}
	
	/**
	 * \brief Check if the channel is one of those allowed by the dispatcher
	 */
	function matches_channel($channel)
	{
		return empty($this->channel_filter) || in_array($channel,$this->channel_filter) ;
	}
	
	/**
	 * \return A string representing the dispatcher
	 */
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
	
	/**
	 * \brief Whether the channel and prefix match this dispatcher
	 */
	function matches(MelanoBotCommand $cmd )
	{
		
		$chan_ok = false;
		if ( is_array($cmd->channel) )
		{
			foreach($cmd->channel as $chan)
				if ( $this->matches_channel($chan) )
				{
					$chan_ok = true;
					break;
				}
		}
		else
			$chan_ok = $this->matches_channel( $cmd->channel ); 
		
		$matches_prefix = $this->prefix && ( $cmd->cmd == $this->prefix ||
				( $cmd->cmd == null && count($cmd->params) > 0 && $cmd->params[0] == $this->prefix ) );
	
		if ( $matches_prefix && !$chan_ok && is_string($cmd->channel) && $cmd->channel[0] != '#' )
			return true;
		
		return $chan_ok && ( !$this->prefix || $matches_prefix  );
	}
	
	/** 
	 * \brief Convert the command (ie handle the customized prefix)
	 */
	function convert(MelanoBotCommand $cmd)
	{
		if ( $this->prefix && count($cmd->params) > 0)
		{
			if ( $cmd->cmd == $this->prefix )
			{
				$cmd = clone $cmd;
				$cmd->cmd = array_shift($cmd->params);
			}
			else if (  $cmd->params[0] == $this->prefix )
			{
				$cmd = clone $cmd;
				array_shift($cmd->params);
			}
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
	
	/**
	 * \brief Install executors, calling the correct add_* function
	 */
	function install($executors)
	{
		if ( !is_array($executors) )
			$executors->install_on($this);
		else
			foreach($executors as $ex)
				$ex->install_on($this);
	}
	
	/**
	 * \brief Check filters
	 */
	function filter(MelanoBotCommand $cmd,MelanoBot $bot,BotData $data)
	{
		foreach($this->filters as $f )
		{
			if(!$f->check($cmd,$bot,$data))
				return false;
		}
		return true;
		
	}
	
	/**
	 * \brief Log that the executor has handled the command
	 */
	private function log($executor)
	{
		Logger::log("irc","!","\x1b[34mHandled by \x1b[1m".get_class($executor).
			"\x1b[22m via \x1b[1m".$this->id()."\x1b[0m",3);
	}
	
	/**
	 * \brief Send \c $cmd to the right executor
	 * \return \b true if the command has been executed and no other dispatcher can do any futher processing
	 */
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
					$this->log($ex);
				}
				else
				{
					foreach($this->raw_executors as $ex)
						if ( $ex->check($cmd,$bot,$data) && $keep_running )
						{
							$ex->execute($cmd,$bot,$data);
							$keep_running = $ex->keep_running();
							$this->log($ex);
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
						$this->log($ex);
					}
			}
		}
		return !$keep_running;
	}
}
