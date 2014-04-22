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
require_once("misc/logger.php");

/**
 * \brief Base for all the executor abstract classes
 */
abstract class ExecutorBase
{
	public $auth;
	
	/// Check that the user can run the executor
	function check_auth($nick,$host,MelanoBot $bot, BotData $data)
	{
		return !$this->auth || $data->user_in_list($this->auth,$bot->get_user($nick,$host));
	}
	
	/// Show help about this command
	abstract function help(MelanoBotCommand $cmd,MelanoBot $bot,BotData $data);
	
	/// Check that with the data provided by cmd, this executor can run
	abstract function check(MelanoBotCommand $cmd,MelanoBot $bot,BotData $data);
	
	/// Run this executor
	abstract function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data);
	
	/**
	 * \brief name to be used in help messages and command lists
	 * \return A string or \b null if this doesn't apply
	 */
	abstract function name();
	
	/// Install on a dispatcher
	abstract function install_on(BotCommandDispatcher $data);
	
	/// whether the command may be executed again
	function keep_running() { return false; }
}

/**
 * \brief Executes an explicit command
 * \note An explicit command has <tt>bot_command->cmd != null</tt>
 */
abstract class CommandExecutor extends ExecutorBase
{
	public $name;         ///< name/trigger
	public $synopsis;     ///< Help synopsis (trigger and commands)
	public $description;  ///< Help Description
	public $reports_error;///< Whether reports error on its own
	public $irc_cmd;      ///< IRC command to be triggered by
	
	function __construct($name,$auth,$synopsis="",$description="",$irc_cmd='PRIVMSG')
	{
		$this->name = $name;
		$this->auth = $auth;
		$this->synopsis = $synopsis;
		$this->description = $description;
		$this->reports_error = false;
		$this->irc_cmd = $irc_cmd;
	}
	
	function check(MelanoBotCommand $cmd,MelanoBot $bot,BotData $data)
	{
		return $this->check_auth($cmd->from,$cmd->host,$bot,$data);
	}
	
	/// Show help about this command
	function help(MelanoBotCommand $cmd,MelanoBot $bot,BotData $data)
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

/**
 * \brief Triggered when the bot is not addressed directly
 */
abstract class RawCommandExecutor extends ExecutorBase
{
	
	function check(MelanoBotCommand $cmd,MelanoBot $bot,BotData $data)
	{
		return $cmd->cmd == null;
	}
	
	/// Show help about this command
	function help(MelanoBotCommand $cmd,MelanoBot $bot,BotData $data)
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

/**
 * \brief Checks whether a command shall be executed or discarded
 */
abstract class Filter extends ExecutorBase
{
	/// equivalent to check()
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		return $this->check($cmd,$bot,$data);
	}
	
	
	/// Show help about this filter
	function help(MelanoBotCommand $cmd,MelanoBot $bot,BotData $data)
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
	
	abstract function execute( MelanoBot $bot, BotData $data);
}