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
}

/// \todo find some way to reconnect $rcon when the server restarts (or just keep spamming)
class Rcon_Communicator extends BotCommandDispatcher implements ExternalCommunicator
{
	
	public $channel;
	public $rcon;
	public $rcon_executors = array();
	
	function Rcon_Communicator($channel,$rcon,$prefix=null)
	{
		parent::__construct(array($channel),$prefix);
		$this->channel = $channel;
		$this->rcon = $rcon;
	}
	
	function initialize(BotData $data)
	{
		$this->rcon->connect();
	}
	
	function finalize(BotData $data)
	{
	}
	
	function install_irc2rcon($ex)
	{
		$this->install($ex);
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
		$packet = $this->rcon->read();
		if ( !$packet->valid )
			return;
		$cmd = new Rcon_Command($packet->payload, $packet->server,$this->channel);
		foreach($this->rcon_executors as $executor)
			if ( $executor->step($cmd, $bot, $data) )
				return;
	}
}

class Rcon2Irc_Say extends Rcon2Irc_Executor
{
	function Rcon2Irc_Say()
	{
		parent::__construct("{\001(.*?)\^7: (.*)}");
	}
	
	function execute(Rcon_Command $cmd, MelanoBot $bot, BotData $data)
	{
		$nick = Color::dp2irc($cmd->params[1]);
		$text = Color::dp2irc($cmd->params[2]);
		$bot->say($cmd->channel,"<$nick\xf> $text");
		return true;
	}
}