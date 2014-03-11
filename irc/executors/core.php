<?php

require_once("irc/bot-driver.php");


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
					if ( $ex->name() && $ex->check_auth($cmd->from,$cmd->host,$data) )
						$list[$ex->name] = $ex;
				foreach($disp->raw_executors as $ex)
					if ( $ex->name() && $ex->check_auth($cmd->from,$cmd->host,$data) )
						$list[$ex->name] = $ex;
				foreach($disp->filters as $ex)
					if ( $ex->name() && $ex->check_auth($cmd->from,$cmd->host,$data) )
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

class Executor_Server extends CommandExecutor
{
	function __construct()
	{
		parent::__construct('server','owner','server','Show server name');
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		$bot->say($cmd->channel,$bot->current_server);
	}
}

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


class Executor_Join extends CommandExecutor
{
	function __construct()
	{
		parent::__construct('join','owner','join #Channel','Make the bot join a channel');
		$this->reports_error = true;
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		if ( $this->check_auth($cmd->from,$cmd->host,$driver)  && isset($cmd->params[0]))
			$bot->join($cmd->params[0]);
		else
			$bot->say($cmd->channel,"No!");
	}
}

class Executor_Part extends CommandExecutor
{
	function __construct()
	{
		parent::__construct('part','owner','part [#Channel]','Make the bot part the current channel or the one specified');
		$this->reports_error = true;
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		if ( $this->check_auth($cmd->from,$cmd->host,$driver) )
		{
			$chan = isset($cmd->params[0]) ? $cmd->params[0] : $cmd->channel;
			$bot->command('PART',$chan);
		}
		else
			$bot->say($cmd->channel,"No!");
	}
}

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
			if ( $driver->user_in_list($list,$nick,$host) )
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
						if ( $driver->remove_from_list($this->list,$nick) )
							$bot->say($cmd->channel,"OK, $nick is no longer in {$this->list}");
						else
                            $bot->say($cmd->channel,"But...");
					}
					else
					{
						$driver->add_to_list($this->list,$nick,$host);
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
				$bot->say($cmd->channel,implode(" ",array_keys($driver->lists[$this->list])));
		}
	}
	
}

class Filter_UserList extends Filter
{
	public $list;
	function __construct($list)
	{
		$this->list = $list;
	}
	
	function check(MelanoBotCommand $cmd,MelanoBot $bot,BotData $driver)
	{
		return !$driver->user_in_list($this->list,$cmd->from,$cmd->host);
	}
}

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
		if ( $n_params > 2 && $cmd->params[$n_params-2] == $this->name && $this->check_auth($cmd->from,$cmd->host,$driver) )
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
	
	function check_auth($nick,$host,BotData $driver)
	{
		foreach($this->executors as $ex )
			if ( $ex->check_auth($nick,$host,$driver) )
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
			if ( $ex->check_auth($cmd->from,$cmd->host,$driver) )
			{
				$ex->help($cmd,$bot,$driver);
				return;
			}
	}
	
}



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
