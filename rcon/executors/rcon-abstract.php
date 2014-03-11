<?php
require_once("irc/executors/abstract.php");

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


abstract class Irc2Rcon_Executor extends CommandExecutor
{
	public $rcon;
	
	function Irc2Rcon_Executor(Rcon $rcon, $name,$auth=null,$synopsis="",$description="",$irc_cmd='PRIVMSG')
	{
		parent::__construct($name,$auth,$synopsis,$description,$irc_cmd);
		$this->rcon = $rcon;
	}
}


