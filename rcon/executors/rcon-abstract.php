<?php
require_once("irc/executors/abstract.php");

abstract class Rcon2Irc_Executor
{
	public $regex;
	
	
	/**
	 * \return True if you want to prevent further processing
	 */
	abstract function execute(Rcon_Command $cmd, MelanoBot $bot, Rcon_Communicator $rcon);
	
	function __construct($regex)
	{
		$this->regex = $regex;
	}
	
	function step(Rcon_Command $cmd, MelanoBot $bot, Rcon_Communicator $rcon)
	{
		if ( preg_match($this->regex,$cmd->data, $cmd->params) )
			return $this->execute($cmd,$bot,$rcon);
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
	abstract function filter(Rcon_Command $cmd,Rcon_Communicator $rcon);
	
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


