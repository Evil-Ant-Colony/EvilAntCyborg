<?php

require_once("rcon/executors/rcon-abstract.php");

/**
 * \brief Handles say /me blah blah
 */
class Rcon2Irc_SayAction extends Rcon2Irc_Executor
{
	function __construct()
	{
		parent::__construct("{\1\^4\* \^7(.*)}");
	}
	
	function execute(Rcon_Command $cmd, MelanoBot $bot, BotData $data, $rcon_data)
	{
		$bot->say($cmd->channel,"\00312*\xf ".Color::dp2irc($cmd->params[1]));
		return true;
	}
}

