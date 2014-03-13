<?php

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