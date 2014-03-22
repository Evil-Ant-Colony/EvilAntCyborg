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
 
require_once("rcon/executors/rcon-abstract.php");

/**
 * \brief Execute arbitrary code from irc (may be dangerous)
 */
class Irc2Rcon_Rcon extends Irc2Rcon_Executor
{
	function __construct(Rcon $rcon, $trigger="rcon", $auth='rcon-admin')
	{
		parent::__construct($rcon,$trigger,$auth,"$trigger command","Send commands directly to rcon");
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		$this->rcon->send($cmd->param_string());
	}
}

/**
 * \brief Execute the given rcon command
 */
class Irc2Rcon_SingleCommand extends Irc2Rcon_Executor
{
	
	function __construct(Rcon $rcon, $command, $auth='rcon-admin')
	{
		parent::__construct($rcon,$command,$auth,"$command [params]","Execute $command on rcon");
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		$this->rcon->send($this->name." ".$cmd->param_string());
	}
}


/**
 * \brief Execute the given rcon command with arguments from irc, then follow with a list of fixed commands
 * 
 * This can be used to send a command that makes some server changes which need to be detected right away
 * An alternative is to use Irc2Rcon_SingleCommand + polling
 */
class Irc2Rcon_Command_Update extends Irc2Rcon_Executor
{
	public $other_commands;
	function __construct(Rcon $rcon, $main_command, $other_commands, $auth='rcon-admin')
	{
		if ( !is_array($other_commands) )
			$other_commands = array($other_commands);
		parent::__construct($rcon,$main_command,$auth,"$main_command [params]",
			"Execute $main_command; ".implode("; ",$other_commands)." on rcon");
		$this->other_commands = $other_commands;
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		$this->rcon->send($this->name." ".$cmd->param_string());
		foreach($this->other_commands as $cmd)
			$this->rcon->send($cmd);
	}
}


/**
 * \brief Call a vote
 */
class Irc2Rcon_VCall extends Irc2Rcon_Executor
{
	function __construct(Rcon $rcon, $trigger="vcall", $auth='rcon-admin')
	{
		parent::__construct($rcon,$trigger,$auth,"$trigger vote [params]","Call a vote over rcon");
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		if ( count($cmd->params) > 0 )
		{
			Rcon_Communicator::set_sv_adminnick($this->data($data),"[IRC] {$cmd->from}");
			$this->rcon->send("vcall ".$cmd->param_string());
		}
	}
}


/**
 * \brief Stop a vote
 */
class Irc2Rcon_VStop extends Irc2Rcon_Executor
{
	function __construct(Rcon $rcon, $trigger="vstop", $auth='rcon-admin')
	{
		parent::__construct($rcon,$trigger,$auth,"$trigger","Stop the current rcon vote");
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		Rcon_Communicator::set_sv_adminnick($this->data($data),"[IRC] {$cmd->from}");
		$this->rcon->send("vstop");
	}
}