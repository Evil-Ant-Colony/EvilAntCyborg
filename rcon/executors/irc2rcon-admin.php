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