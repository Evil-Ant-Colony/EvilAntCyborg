<?php
require_once("irc/executors/abstract.php");

abstract class Rcon2Irc_Executor
{
	public $regex;
	
	
	/**
	 * \return True if you want to prevent further processing
	 */
	abstract function execute(Rcon_Command $cmd, MelanoBot $bot, BotData $data, $rcon_data);
	
	function __construct($regex)
	{
		$this->regex = $regex;
	}
	
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

abstract class Rcon2Irc_Filter
{
	
	/**
	 * \return False if you want to prevent further processing
	 */
	abstract function filter(Rcon_Command $cmd,$rcon_data);
	
	function install_on(Rcon_Communicator $comm)
	{
		$comm->rcon_filters []= $this;
	}
} 


abstract class Irc2Rcon_Executor extends CommandExecutor
{
	public $rcon;
	
	function __construct(Rcon $rcon, $name,$auth=null,$synopsis="",$description="",$irc_cmd='PRIVMSG')
	{
		parent::__construct($name,$auth,$synopsis,$description,$irc_cmd);
		$this->rcon = $rcon;
	}
}


