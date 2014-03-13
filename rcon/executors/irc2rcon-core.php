<?php

require_once("rcon/executors/rcon-abstract.php");

class Irc2Rcon_RawSay extends RawCommandExecutor
{
	public $say_command;
	public $action_command;
	public $rcon;
	
	function __construct(Rcon $rcon, $say_command='_ircmessage %s ^7: %s',$action_command='_ircmessage "^4*^3 %s" ^3 %s')
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
	function __construct(Rcon $rcon, $trigger="who", $auth=null)
	{
		parent::__construct($rcon,$trigger,$auth,"$trigger","List players on {$rcon->read}");
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		$player_manager = $this->data($data)->player;
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

class Irc2Rcon_Status extends Irc2Rcon_Executor
{
	function __construct(Rcon $rcon, $trigger="status", $auth='rcon-admin')
	{
		parent::__construct($rcon,$trigger,$auth,"$trigger","Show players and server information");
	}
	
	function settings($channel, MelanoBot $bot, $rcon_data)
	{
		$gametype = "unknown";
		if ( isset($rcon_data->gametype) )
			$gametype = Rcon_Communicator::gametype_name($rcon_data->gametype);

		$bot->say($channel,"Map: \00304{$rcon_data->map}\017, Game: \00304$gametype\017");
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		$rcon_data = $this->data($data);
		$player_manager = $rcon_data->player;
		/// \todo status only on users matching the parameters
		if ( $player_manager->count_all() > 0 )
		{
			$bot->say($cmd->channel,sprintf("\002%-21s %2s %4s %5s %-4s %s\xf", 
				"ip address", "pl", "ping", "frags", "slot", "name" ));
			
			$spects = 0;
			foreach($player_manager->all() as $player)
			{
				
				$frags = $player->frags;
				if ( $frags == "-666" )
				{
					$frags = "spect";
					if ( !$player->is_bot() )
						$spects++;
				}
				
				$bot->say($cmd->channel,
					sprintf("%-21s %2s %4s %5s  #%-2s %s",
						$player->ip, 
						$player->pl,
						$player->ping,
						$frags,
						$player->slot,
						Color::dp2irc($player->name)
				));
			}
			
			
			$bot->say($cmd->channel,"Players: \00304".($player_manager->count_players()-$spects).
				"\xf active, \00304$spects\xf spectators, \00304".$player_manager->count_bots().
				"\017 bots, \00304".$player_manager->count_all()."\xf/{$player_manager->max} total");
		}
		else
			$bot->say($cmd->channel,"No users in server");
		
		$this->settings($cmd->channel,$bot,$rcon_data);
	}
}
