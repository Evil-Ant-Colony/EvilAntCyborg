<?php

require_once("rcon/executors/rcon-abstract.php");



class Irc2Rcon_RawSayAdmin extends RawCommandExecutor
{
	public $say_command;
	public $rcon;
	
	function __construct(Rcon $rcon, $say_command='say ^7')
	{
		$this->say_command=$say_command;
		$this->rcon = $rcon;
	}
	
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		$rcon_data = $data->rcon["{$this->rcon->read}"];
		Rcon_Communicator::set_sv_adminnick($rcon_data,"[IRC] {$cmd->from}");
		$text = str_replace(array('\\','"'),array('\\\\','\"'),$cmd->param_string());
		if ( preg_match("{^\1ACTION ([^\1]*)\1$}", $text, $match) )
			$text = $match[1];
		$this->rcon->send($this->say_command." ".Color::irc2dp($text));
		Rcon_Communicator::restore_sv_adminnick($rcon_data);
	}
}