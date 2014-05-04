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

require_once("irc/bot-driver.php");

/**
 * Shows the available commands or details on a specific command
 */
class Executor_Help extends CommandExecutor
{
	function __construct()
	{
		parent::__construct("help",null,'help [command]','Guess what this does...');
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		$list = array();
		foreach ( $data->driver->dispatchers as $disp )
		{
			if ( $disp->matches_channel($cmd->channel) )
			{
				foreach($disp->executors as $name => $ex)
					if ( $ex->name() && $ex->check_auth($cmd->from,$cmd->host,$bot,$data) )
						$list[$ex->name] = $ex;
				foreach($disp->raw_executors as $ex)
					if ( $ex->name() && $ex->check_auth($cmd->from,$cmd->host,$bot,$data) )
						$list[$ex->name] = $ex;
				foreach($disp->filters as $ex)
					if ( $ex->name() && $ex->check_auth($cmd->from,$cmd->host,$bot,$data) )
						$list[$ex->name] = $ex;
			}
		}
		ksort($list);
		
		if ( count($cmd->params) > 0 )
		{
			foreach ( $cmd->params as $hc )
			{
				$hc = strtolower($hc);
				if ( isset($list[$hc]) )
					$list[$hc]->help($cmd,$bot,$data);
				else
					$bot->say($cmd->channel,"You can't do $hc");
			}
			if ( count($cmd->params) > 1 )
				$bot->say($cmd->channel,"(End of help list)");
		}
		else
		{
			$bot->say($cmd->channel, implode(' ',array_keys($list)));
		}
		
	}
	
}

/**
 * Allows owners to shut down the bot
 */
class Executor_Quit extends CommandExecutor
{
	function __construct($cmd="quit")
	{
		parent::__construct($cmd,'owner',$cmd,'Shut down the bot');
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		$bot->quit(); 
		$bot->auto_restart = false;
	}
}


/**
 * Allows owners to reconnect to the network (using a different server if available)
 */
class Executor_Reconnect extends CommandExecutor
{
	function __construct()
	{
		parent::__construct('reconnect','owner','reconnect','Reconnect to a different server');
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		$bot->reconnect("Reconnecting...");
	}
}

/**
 * Show the IRC server the bot is connected to
 */
class Executor_Server extends CommandExecutor
{
	function __construct()
	{
		parent::__construct('server','owner','server','Show server name');
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		$bot->say($cmd->channel,$bot->current_server());
	}
}

/**
 * Quit and restart the bot (works when the bot has been launched with run-bot.sh)
 */
class Executor_Restart extends CommandExecutor
{
	public $massage;
	function __construct($message="See y'all in a sec")
	{
		$this->message = $message;
		parent::__construct("restart",'owner',"restart",'Restart the bot (quit and rejoin)');
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		$bot->quit($this->message);
		$bot->auto_restart = true;
	}
}

/**
 * Creates a temporary file so that (run-bot.sh knows whether to restart the bot)
 */
class Post_Restart extends StaticExecutor
{
	public $restart_file;
	
	function __construct($restart_file = null)
	{
		if ( !$restart_file )
		{
			global $argv;
			$this->restart_file = ".restart_".basename($argv[0],".php");
		}
		else
			$this->restart_file = $restart_file;
	}
	
	function execute(MelanoBot $bot, BotData $driver)
	{
		if ( $bot->connection_status() == MelanoBot::DISCONNECTED && $bot->auto_restart )
			touch($this->restart_file);
	}
}

/**
 * Makes the bot join the given channel
 */
class Executor_Join extends CommandExecutor
{
	function __construct()
	{
		parent::__construct('join','owner','join #Channel','Make the bot join a channel');
		$this->reports_error = true;
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		if ( $this->check_auth($cmd->from,$cmd->host,$bot,$driver)  && isset($cmd->params[0]))
			$bot->join($cmd->params[0]);
		else
			$bot->say($cmd->channel,"No!");
	}
}

/**
 * Part from a channel (or the current channel if none is provided)
 */
class Executor_Part extends CommandExecutor
{
	function __construct()
	{
		parent::__construct('part','owner','part [#Channel]','Make the bot part the current channel or the one specified');
		$this->reports_error = true;
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		if ( $this->check_auth($cmd->from,$cmd->host,$bot,$driver) )
		{
			$chan = isset($cmd->params[0]) ? $cmd->params[0] : $cmd->channel;
			$bot->command('PART',$chan);
		}
		else
			$bot->say($cmd->channel,"No!");
	}
}

/**
 * Changes the bot nickname
 */
class Executor_Nick extends CommandExecutor
{
	function __construct()
	{
		parent::__construct('nick','owner','nick Nickname','Change nickname');
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		if ( isset($cmd->params[0]) )
		{
			$bot->set_nick($cmd->params[0]);
		}
		else
			$bot->say($cmd->channel,"Nick?");
	}
}

/**
 * Add or remove a IRC user from a named list (eg: admin, blacklist etc).
 * This can change the commands said user can access
 */
class Executor_UserList extends CommandExecutor
{
	public $list, $exclude;
	
	function __construct($list, $auth,$exclude=array(), $listener="")
	{
		if ( !$listener )
			$listener = $list;
		$this->list = $list;
		$this->exclude = $exclude;
		parent::__construct($listener,$auth,"$listener [ +|add|-|rm Nickname [Host] ]|[clear]","Add a user to the $list list");
	}
	
	function check_nick($nick,$host,MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		if ( $nick == $bot->nick )
		{
			$bot->say($cmd->channel,"Who, me?");
			return false;
		}
		foreach($this->exclude as $list => $msg)
		{
			if ( $driver->user_in_list($list,$bot->get_user($nick,$host)) )
			{
				$bot->say($cmd->channel,str_replace("%",$nick,$msg));
				return false;
			}
		}
		return true;
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		if ( isset($cmd->params[0]) )
		{
			$remove = false;
			$nick_i = 1;
			if ( $cmd->params[0] == "add" || $cmd->params[0] == "+" )
				$remove = false;
			elseif ( $cmd->params[0] == "rm" || $cmd->params[0] == "-" ) 
				$remove = true;
			elseif ( $cmd->params[0] == "clear" )
			{
				$driver->lists[$this->list] = array();
				$bot->say($cmd->channel,"OK, {$this->list} cleared");
					
				return;
			}
			else
				$nick_i = 0;
				
			if ( !isset($cmd->params[$nick_i]) )
				$bot->say($cmd->channel,"Who?");
			else
			{
				$nick = $cmd->params[$nick_i];
				$host = isset($cmd->params[$nick_i+1]) ? $cmd->params[$nick_i+1] : null;
				
				if ( $this->check_nick($nick,$host,$cmd,$bot,$driver) )
				{
					if ( $remove )
					{
						if ( $driver->remove_from_list_nick($this->list,$nick) )
							$bot->say($cmd->channel,"OK, $nick is no longer in {$this->list}");
						else
                            $bot->say($cmd->channel,"But...");
					}
					else
					{
						$driver->add_to_list($this->list,$bot->get_user($nick,$host));
						$bot->say($cmd->channel,"OK, $nick is in {$this->list}");
					}
				}
			}
		}
		else
		{
			if ( !isset($driver->lists[$this->list]) || count($driver->lists[$this->list]) == 0 )
				$bot->say($cmd->channel,"(Empty list)");
			else
			{
				$users = array();
				foreach($driver->lists[$this->list] as $user)
				{
					$users []= $user->nick;
				}
				$bot->say($cmd->channel,implode(" ",$users));
			}
		}
	}
	
}

/**
 * Prevents user in the given list to perform any command (blacklist)
 */
class Filter_UserList extends Filter
{
	public $list;
	function __construct($list)
	{
		$this->list = $list;
	}
	
	function check(MelanoBotCommand $cmd,MelanoBot $bot,BotData $driver)
	{
		return !$driver->user_in_list($this->list,$bot->get_user($cmd->from,$cmd->host));
	}
}

/**
 * \brief Sinmple filter by IRC nick
 *
 * Prevents users in the list to trigger any command (useful to ignore other bots)
 */
class Filter_UserArray extends Filter
{
	public $list;
	function __construct($list)
	{
		$this->list = $list;
	}
	
	function check(MelanoBotCommand $cmd,MelanoBot $bot,BotData $driver)
	{
		return !in_array($cmd->from,$this->list);
	}
}

/**
 * Allows some users to execute bot commands from a channel (or private message to another)
 */
class Filter_ChanHax extends Filter
{
	
	function __construct($name,$auth)
	{
		$this->auth = $auth;
		$this->name = $name;
		$this->synopsis = "[command] $name #Channel";
		$this->dewscription = 'Execute the command on the given channel';
	}
	
	function check(MelanoBotCommand $cmd,MelanoBot $bot,BotData $driver)
	{
		$n_params=count($cmd->params);
		if ( $n_params > 2 && $cmd->params[$n_params-2] == $this->name && $this->check_auth($cmd->from,$cmd->host,$bot,$driver) )
		{
			$cmd->channel = array_pop($cmd->params);
			$chanhax = true;
			array_pop($cmd->params); // remove chanhax
		}
		return true;
	}
	
	function name()
	{
		return $this->name;
	}
}


/**
 * Executes a raw IRC command
 */
class Executor_RawIRC extends CommandExecutor
{
	function __construct($trigger)
	{
		parent::__construct($trigger,'owner',"$trigger IRC_CMD [irc_options...]",'Execute an arbitrary IRC command');
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		if ( count($cmd->params) )
		{
			$command = array_shift($cmd->params);
			$bot->command($command, $cmd->param_string() ); 
		}
	}
}


/**
 * \brief Handles multiple executors with different permissions but the same trigger
 */
class Executor_Multi extends CommandExecutor
{
	public $executors;
	
	function __construct($trigger,$executors)
	{
		parent::__construct($trigger,null);
		$this->executors = $executors;
	}
	
	function check_auth($nick,$host,MelanoBot $bot,BotData $driver)
	{
		foreach($this->executors as $ex )
			if ( $ex->check_auth($nick,$host,$bot,$driver) )
				return true;
		return false;
	}
	
	
	function check(MelanoBotCommand $cmd,MelanoBot $bot,BotData $driver)
	{
		foreach($this->executors as $ex )
			if ( $ex->check($cmd,$bot,$driver) )
				return true;
		return false;
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		foreach($this->executors as $ex )
			if ( $ex->check($cmd,$bot,$driver) )
			{
				$ex->execute($cmd,$bot,$driver);
				return;
			}
	}
	
	function help(MelanoBotCommand $cmd,MelanoBot $bot,BotData $driver)
	{
		foreach($this->executors as $ex )
			if ( $ex->check_auth($cmd->from,$cmd->host,$bot,$driver) )
			{
				$ex->help($cmd,$bot,$driver);
				return;
			}
	}
	
}


/**
 * Dump debugging information to stdout
 */
class Executor_StdoutDump extends CommandExecutor
{
	function __construct($trigger='debug')
	{
		parent::__construct($trigger,'owner',"$trigger",'Print the object structure on stdout');
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		print_r($driver);
	}
}


/**
 * \brief Show license and sourcecode location
 */
class Executor_License extends CommandExecutor
{
	public $sources;
	
	function __construct($sources=null, $trigger="license")
	{
		if ( !$sources )
			$sources = MelanoBot::$source_url;
		parent::__construct($trigger,null,$trigger,"Show licensing info");
		$this->sources = $sources;
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		$bot->say($cmd->channel,"AGPLv3+ (http://www.gnu.org/licenses/agpl-3.0.html), Sources: $this->sources");
	}
	
}