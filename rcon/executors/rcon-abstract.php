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

/**
 * \brief Execute in response to a rcon line
 */
abstract class Rcon2Irc_Executor
{
	public $regex; ///< Regex to match the line against
	
	
	/**
	 * \return True if you want to prevent further processing
	 * \note Best if returns \b true if something has been sent to IRC, to avoid multiple
	 * messages regarding the same thing; \b false if it has just gathered some data,
	 * so other executors can do the same.
	 */
	abstract function execute(Rcon_Command $cmd, MelanoBot $bot, Rcon_Communicator $rcon);
	
	function __construct($regex)
	{
		$this->regex = $regex;
	}
	
	/**
	 * \brief Checks and executes the command
	 * \return \b true if the command has been executed and should no longer be processed, \b false otherwise
	 */
	function step(Rcon_Command $cmd, MelanoBot $bot, Rcon_Communicator $rcon)
	{
		if ( preg_match($this->regex,$cmd->data, $cmd->params) )
			return $this->execute($cmd,$bot,$rcon);
		return false;
	}
	
	function install_on(Rcon_Communicator $comm)
	{
		$comm->rcon_executors []= $this;
	}
}

/**
 * \brief Filter out Rcon commands
 */
abstract class Rcon2Irc_Filter
{
	
	/**
	 * \return False if you want to prevent further processing
	 */
	abstract function filter(Rcon_Command $cmd,Rcon_Communicator $rcon);
	
	function install_on(Rcon_Communicator $comm)
	{
		$comm->rcon_filters []= $this;
	}
} 

/**
 * \brief IRC Executor which may send data to rcon
 */
abstract class Irc2Rcon_Executor extends CommandExecutor
{
	public $rcon;
	
	function __construct(Rcon $rcon, $name,$auth=null,$synopsis="",$description="",$irc_cmd='PRIVMSG')
	{
		parent::__construct($name,$auth,$synopsis,$description,$irc_cmd);
		$this->rcon = $rcon;
	}
	
	function data(BotData $data)
	{
		return $data->rcon["{$this->rcon->read}"];
	}
	
	function out_prefix()
	{
		return isset($this->comm->out_prefix) ? $this->comm->out_prefix : "";
	}
}


