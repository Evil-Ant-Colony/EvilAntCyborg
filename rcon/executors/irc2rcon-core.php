<?php

require_once("rcon/executors/rcon-abstract.php");

class Irc2Rcon_RawSay extends RawCommandExecutor
{
	public $say_command;
	public $action_command;
	public $rcon;
	
	function Irc2Rcon_RawSay(Rcon $rcon, $say_command='_ircmessage %s ^7: %s',$action_command='_ircmessage "^4*^3 %s" ^3 %s')
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
		$player_manager = $data->rcon["{$this->rcon->read}"]->player;
		$players = array();
		foreach($player_manager->all_no_bots() as $player)
		{
			$players[]= Color::dp2irc($player->name);
		}
				
		if ( !empty($players) )
			$bot->say($cmd->channel,"\00304".count($players)."\xf/\00304{$player_manager->max}\xf: ".implode(", ",$players));
		else
			$bot->say($cmd->channel,"Server is empty");
	}
}
