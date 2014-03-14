<?php


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

/**
 * \note Requires Rcon2Irc_GetCvars or similar, g_maplist (slow) polling is reccommended
 */
class Irc2Rcon_Maps extends Irc2Rcon_Executor
{
	public $max_count;///< Maximum number of matches to show an actual list
	
	function __construct(Rcon $rcon, $max_count=6, $trigger="maps", $auth=null)
	{
		parent::__construct($rcon,$trigger,$auth,"$trigger [pattern]","Find maps matching [pattern]");
		$this->max_count = $max_count;
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		$rcon_data = $this->data($data);
		if ( !isset($rcon_data->cvar) || !isset($rcon_data->cvar["g_maplist"]) )
		{
			$this->rcon->send("g_maplist");
			$bot->say($cmd->channel,"Map list not initialized, try again in a few moments");
		}
		else
		{
			$maps = explode(" ",$rcon_data->cvar["g_maplist"]);
			$nmaps = count($maps);
			$pattern = str_replace(array('\\',"/"),array('\\\\',"\\/"),trim($cmd->param_string()));
			if ( $pattern )
			{
				$maps = preg_grep("/$pattern/",$maps);
				$nmatch = count($maps);
				$bot->say($cmd->channel,"\00310$nmatch\xf/\00304$nmaps\xf maps match");
				if ( $nmatch <= $this->max_count )
					foreach($maps as $map)
						$bot->say($cmd->channel,"\00303$map",-$this->max_count);
			}
			else
			{
				$bot->say($cmd->channel,"\00304$nmaps\xf maps");
			}
		}
	}
}

/**
 * \note requires Rcon2Irc_UpdateBans, banlist (slow) polling is reccommended
 */
class Irc2Rcon_Banlist extends Irc2Rcon_Executor
{
	
	function __construct(Rcon $rcon, $trigger="banlist", $auth='rcon-admin')
	{
		parent::__construct($rcon,$trigger,$auth,"$trigger [refresh]","Show active bans");
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		$rcon_data = $this->data($data);
		if ( isset($cmd->params[0]) && $cmd->params[0] == 'refresh')
			$this->rcon->send("banlist");
		else if ( empty($rcon_data->bans) )
			$bot->say($cmd->channel,"No active bans");
		else
			foreach ( $rcon_data->bans as $id => $ban )
				$bot->say($cmd->channel,sprintf(
					"#\00304%-3s \00310%-21s\xf %s seconds",
					$id,$ban->ip,$ban->time));
	}
}
