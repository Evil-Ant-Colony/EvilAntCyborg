<?php

require_once("rcon.php");
require_once("data-source.php");
require_once("executors-abstract.php");

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
	abstract function execute(Rcon_Command $cmd, MelanoBot $bot, BotData $data);
	
	function step(Rcon_Command $cmd, MelanoBot $bot, BotData $data)
	{
		if ( preg_match("{\001(.*?)\^7: (.*)}",$cmd->data, $cmd->params) )
			return $this->execute($cmd,$bot,$data);
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
	
	function Rcon_Communicator($channel,$rcon,$prefix=null)
	{
		parent::__construct(array($channel),$prefix);
		$this->channel = $channel;
		$this->rcon = $rcon;
	}
	
	function initialize(BotData $data)
	{
		$this->rcon->connect();
		$this->setup_server();
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
			$this->rcon->send("log_dest_udp {$this->rcon->read}");
			
			// unknown command hax to call setup_server
			
			foreach($this->poll_commands as $pc )
				$this->rcon->send($pc);
			$this->poll_time = $time + $this->poll_interval;
		}
		
		$packet = $this->rcon->read();
		if ( !$packet->valid )
			return;
		$cmd = new Rcon_Command($packet->payload, $packet->server,$this->channel);
		foreach($this->rcon_executors as $executor)
			if ( $executor->step($cmd, $bot, $data) )
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
	
	function Irc2Rcon_Executor($rcon, $name,$auth=null,$synopsis="",$description="",$irc_cmd='PRIVMSG')
	{
		parent::__construct($name,$auth,$synopsis,$description,$irc_cmd);
		$this->rcon = $rcon;
	}
}


class Irc2Rcon_RawSay extends RawCommandExecutor
{
	public $rcon;
	public $say_command;
	public $action_command;
	
	function Irc2Rcon_RawSay($rcon, $say_command="say ^3[IRC] %s:^7 %s",$action_command="say ^4*^3 [IRC] %s %s")
	{
		$this->say_command=$say_command;
		$this->action_command = $action_command;
		$this->rcon = $rcon;
	}
	
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		$text = str_replace(array('\\','"'),array('\\\\','\"'),$cmd->param_string());
		if ( preg_match("{^\1ACTION ([^\1]*)\1$}", $text, $match) )
			$this->rcon->send(sprintf($this->action_command,$cmd->from,Color::irc2dp($match[1])));
		else
			$this->rcon->send(sprintf($this->say_command,$cmd->from,Color::irc2dp($text)));
	}
}

class Rcon2Irc_Say extends Rcon2Irc_Executor
{
	function Rcon2Irc_Say()
	{
		parent::__construct("{\1(.*?)\^7: (.*)}");
	}
	
	function execute(Rcon_Command $cmd, MelanoBot $bot, BotData $data)
	{
		$nick = Color::dp2irc($cmd->params[1]);
		$text = Color::dp2irc($cmd->params[2]);
		$bot->say($cmd->channel,"<$nick\xf> $text");
		return true;
	}
}