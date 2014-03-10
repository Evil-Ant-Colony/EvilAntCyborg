<?php

require_once("rcon.php");
require_once("data-source.php");
require_once("executors-abstract.php");
require_once("rcon-players.php");

class Rcon_Command
{
	public $data;
	public $server;
	public $channel;
	public $params = array();
	
	function Rcon_Command($data, Rcon_Server $server, $irc_channel)
	{
		$this->data = $data;
		$this->server = $server;
		$this->channel = $irc_channel;
	}
}

abstract class Rcon2Irc_Executor
{
	public $regex;
	
	function Rcon2Irc_Executor($regex)
	{
		$this->regex = $regex;
	}
	
	/**
	 * \return True if you want to prevent further processing
	 */
	abstract function execute(Rcon_Command $cmd, MelanoBot $bot, BotData $data, $rcon_data);
	
	function step(Rcon_Command $cmd, MelanoBot $bot, BotData $data, $rcon_data)
	{
		if ( preg_match($this->regex,$cmd->data, $cmd->params) )
			return $this->execute($cmd,$bot,$data,$rcon_data);
		return false;
	}
	
	function install_on(Rcon_Communicator $comm)
	{
		$comm->rcon_executors []= $this;
	}
}

/// \todo find some way to reconnect $rcon when the server restarts (or just keep spamming)
class Rcon_Communicator extends BotCommandDispatcher implements ExternalCommunicator
{
	public $channel;
	public $rcon;
	public $rcon_executors = array();
	public $poll_commands = array();
	public $poll_interval = 30;
	private $poll_time = 0;
	private $rcon_data;
	
	function Rcon_Communicator($channel,Rcon $rcon,$prefix=null)
	{
		parent::__construct(array($channel),$prefix);
		$this->channel = $channel;
		$this->rcon = $rcon;
		$this->poll_commands []= "status 1";
	}
	
	function initialize(BotData $data)
	{
		$this->rcon->connect();
		$this->setup_server();
		if ( !isset($data->rcon) )
			$data->rcon = array();
		
		if ( ! isset($data->rcon["{$this->rcon->read}"]) )
		{
			$data->rcon["{$this->rcon->read}"] = new StdClass();
			$data->rcon["{$this->rcon->read}"]->rcon = $this->rcon;
			$data->rcon["{$this->rcon->read}"]->player = new PlayerManager;
		}
		$this->rcon_data = $data->rcon["{$this->rcon->read}"];
	}
	
	function finalize(BotData $data)
	{
	}
	
	function install_rcon2irc($executors)
	{
		if ( !is_array($executors) )
			$this->rcon_executors[]=$executors;
		else
			$this->rcon_executors = array_merge($executors,$this->rcon_executors);
	}
	
	function step(MelanoBot $bot, BotData $data)
	{
		$time = time();
		if ( $time > $this->poll_time )
		{
			// ensure we are always listening correctly
			$this->rcon->send("log_dest_udp {$this->rcon->read}");
			
			// unknown command hax to call setup_server, use Data to notify when coming online/offline
			
			foreach($this->poll_commands as $pc )
				$this->rcon->send($pc);
			$this->poll_time = $time + $this->poll_interval;
		}
		
		$packet = $this->rcon->read();
		
		if ( !$packet->valid || strlen($packet->payload) == 0 )
			return;
			
		$cmd = new Rcon_Command($packet->payload, $packet->server,$this->channel);
		foreach($this->rcon_executors as $executor)
			if ( $executor->step($cmd, $bot, $data, $this->rcon_data) )
				return;
	}
	
	private function setup_server()
	{
		//$this->rcon->send("log_dest_udp {$this->rcon->read}");
		$this->rcon->send("sv_logscores_console 0");
		$this->rcon->send("sv_logscores_bots 1");
		$this->rcon->send("sv_eventlog 1");
		$this->rcon->send("sv_eventlog_console 1");
	}
}

abstract class Irc2Rcon_Executor extends CommandExecutor
{
	public $rcon;
	
	function Irc2Rcon_Executor(Rcon $rcon, $name,$auth=null,$synopsis="",$description="",$irc_cmd='PRIVMSG')
	{
		parent::__construct($name,$auth,$synopsis,$description,$irc_cmd);
		$this->rcon = $rcon;
	}
}


class Irc2Rcon_RawSay extends RawCommandExecutor
{
	public $say_command;
	public $action_command;
	public $rcon;
	
	function Irc2Rcon_RawSay(Rcon $rcon, $say_command="say ^3[IRC] %s:^7 %s",$action_command="say ^4*^3 [IRC] %s %s")
	{
		$this->say_command=$say_command;
		$this->action_command = $action_command;
		$this->rcon = $rcon;
	}
	
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		$text = str_replace(array('\\','"'),array('\\\\','\"'),$cmd->param_string());
		if ( preg_match("{^\1ACTION ([^\1]*)\1$}", $text, $match) )
			$this->rcon->send(sprintf($this->action_command,$cmd->from,Color::irc2dp($match[1])));
		else
			$this->rcon->send(sprintf($this->say_command,$cmd->from,Color::irc2dp($text)));
	}
}

class Irc2Rcon_Who extends Irc2Rcon_Executor
{
	function Irc2Rcon_Who(Rcon $rcon, $trigger="who", $auth=null)
	{
		parent::__construct($rcon,$trigger,$auth,"$trigger","List players on {$rcon->read}");
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		$players = array();
		$player_manager = $data->rcon["{$this->rcon->read}"]->player;
		foreach($player_manager->all() as $player)
			if ( $player )
				$players[]= Color::dp2irc($player->nick);
		$bot->say($cmd->channel,"\00304{$player_manager->count}\xf: ".implode(", ",$players));
	}
}


class Rcon2Irc_Say extends Rcon2Irc_Executor
{
	function Rcon2Irc_Say()
	{
		parent::__construct("{\1(.*?)\^7: (.*)}");
	}
	
	function execute(Rcon_Command $cmd, MelanoBot $bot, BotData $data, $rcon_data)
	{
		$nick = Color::dp2irc($cmd->params[1]);
		$text = Color::dp2irc($cmd->params[2]);
		$bot->say($cmd->channel,"<$nick\xf> $text");
		return true;
	}
}


class Rcon2Irc_UpdatePlayerNumber extends Rcon2Irc_Executor
{
	function Rcon2Irc_UpdatePlayerNumber()
	{
		parent::__construct("{players:  (\d+) active \((\d+) max\)}");
	}
	
	function execute(Rcon_Command $cmd, MelanoBot $bot, BotData $data, $rcon_data)
	{
		$rcon_data->player->max = $cmd->params[2];
		$rcon_data->player->count = $cmd->params[1];
	}
}

class Rcon2Irc_Join extends Rcon2Irc_Executor
{
	public $show_number;
	public $show_ip;
	
	function Rcon2Irc_Join($show_number = true, $show_ip = false)
	{
		parent::__construct("{:join:(\d+):(\d+):([^:]*):(.*)}");
		$this->show_number = $show_number;
		$this->show_ip = $show_ip;
	}
	
	function execute(Rcon_Command $cmd, MelanoBot $bot, BotData $data, $rcon_data)
	{
		$player = new RconPlayer();
		list ($player->id, $player->slot, $player->ip, $player->nick) = array_slice($cmd->params,1);
		
		/// \todo how to handle bots?
		$rcon_data->player->add($player);
		
		if ( $player->is_bot() )
			return;
		
		$msg = "\00309+ join\xf: ".Color::dp2irc($player->nick);
		
		if ( $this->show_ip )
			$msg .= " (\00304{$player->ip}\xf)";
		
		if ( $this->show_number )
			$msg .= " [\00304".$rcon_data->player->count."\xf/\00304".$rcon_data->player->max."\xf]";
			
		$bot->say($cmd->channel,$msg." -- IP={$player->ip}, ID={$player->id}, SLOT={$player->slot} ");
		
		return true;
	}
}
/// \todo common class with function that generates the message
/// \todo show only non-bots in total
class Rcon2Irc_Part extends Rcon2Irc_Executor
{
	public $show_number;
	public $show_ip;
	
	function Rcon2Irc_Part($show_number = true, $show_ip = false)
	{
		parent::__construct("{:part:(\d+)}");
		$this->show_number = $show_number;
		$this->show_ip = $show_ip;
	}
	
	
	function execute(Rcon_Command $cmd, MelanoBot $bot, BotData $data, $rcon_data)
	{
		$player = $rcon_data->player->remove($cmd->params[1]);
		if ( $player && !$player->is_bot() )
		{
			$msg = "\00304- part\xf: ".Color::dp2irc($player->nick);
			
			if ( $this->show_ip )
				$msg .= " (\00304{$player->ip}\xf)";
				
			if ( $this->show_number )
				$msg .= " [\00304".$rcon_data->player->count."\xf/\00304".$rcon_data->player->max."\xf]";
				
			$bot->say($cmd->channel,$msg." -- IP={$player->ip}, ID={$player->id}, SLOT={$player->slot} ");
		}
		return true;
	}
}

class Rcon2Irc_Endmatch extends Rcon2Irc_Executor
{
	
	function Rcon2Irc_Endmatch()
	{
		parent::__construct("{:end}");
	}
	
	
	function execute(Rcon_Command $cmd, MelanoBot $bot, BotData $data, $rcon_data)
	{
		$rcon_data->player->clear();
		return true;
	}
}