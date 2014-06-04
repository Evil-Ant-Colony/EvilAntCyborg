<?php
/**
 * \file
 * \author Mattia Basaglia
 * \copyright Copyright 2013-2014 Mattia Basaglia
 * \section License
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */


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
			$bot->say($cmd->channel,$this->out_prefix()."\00304".count($players).
				"\xf/\00304{$player_manager->max}\xf: ".implode(", ",$players));
		else
			$bot->say($cmd->channel,$this->out_prefix()."Server is empty");
	}
}

class Irc2Rcon_Status extends Irc2Rcon_Executor
{
	public $private;
	
	function __construct(Rcon $rcon, $private = true, $trigger="status", $auth='rcon-admin')
	{
		parent::__construct($rcon,$trigger,$auth,"$trigger","Show players and server information");
		$this->private = $private;
	}
	
	function settings($channel, MelanoBot $bot, $rcon_data)
	{
		$gametype = "unknown";
		if ( isset($rcon_data->gametype) )
			$gametype = Rcon_Communicator::gametype_name($rcon_data->gametype);

		$bot->say($channel,"Map: \00304{$rcon_data->map}\017, Game: \00304$gametype\017");
		
		
		if ( !empty($rcon_data->mutators) )
		{
			$bot->say($channel,"Mutators: ".implode(", ",$rcon_data->mutators));
		}
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		$channel = $this->private ? $cmd->from : $cmd->channel;
		
		if ( !$this->comm->is_connected() )
		{
			$bot->say($channel,$this->out_prefix()."Server not connected");
			return;
		}
			
		$rcon_data = $this->data($data);
		$player_manager = $rcon_data->player;
		/// \todo status only on users matching the parameters
		if ( $player_manager->count_all() > 0 )
		{
			
			$bot->say($channel,sprintf("\002%-21s %2s %4s %5s %-4s %s\xf", 
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
				
				$bot->say($channel,
					sprintf("%-21s %2s %4s %5s  #%-2s %s",
						$player->ip, 
						$player->pl,
						$player->ping,
						$frags,
						$player->slot,
						Color::dp2irc($player->name)
				));
			}
			
			
			$bot->say($channel,$this->out_prefix()."Players: \00304".($player_manager->count_players()-$spects).
				"\xf active, \00304$spects\xf spectators, \00304".$player_manager->count_bots().
				"\017 bots, \00304".$player_manager->count_all()."\xf/{$player_manager->max} total");
		}
		else
			$bot->say($channel,$this->out_prefix()."No users in server");
		
		$this->settings($channel,$bot,$rcon_data);
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
			$bot->say($cmd->channel,$this->out_prefix()."Map list not initialized, try again in a few moments");
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
				$bot->say($cmd->channel,$this->out_prefix()."\00310$nmatch\xf/\00304$nmaps\xf maps match");
				if ( $nmatch <= $this->max_count )
					foreach($maps as $map)
						$bot->say($cmd->channel,"\00303$map",-$this->max_count);
			}
			else
			{
				$bot->say($cmd->channel,$this->out_prefix()."\00304$nmaps\xf maps");
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



/**
 * \brief Show information about the server
 */
class Irc2Rcon_Server extends Irc2Rcon_Executor
{
	function __construct(Rcon $rcon, $trigger="server", $auth=null)
	{
		parent::__construct($rcon,$trigger,$auth,"$trigger [ip|stats|game]","Show server information");
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		$rcon_data = $this->data($data);
		if ( count($cmd->params) == 0 )
		{
			$bot->say($cmd->channel,$this->out_prefix().$rcon_data->hostname);
			return;
		}
		
		switch ( $cmd->params[0] )
		{
			case 'ip': 
				$bot->say($cmd->channel,$this->out_prefix().$this->comm->write_server);
				break;
			case 'stats':
				$bot->say($cmd->channel,$this->out_prefix().(!empty($rcon_data->stats)?$rcon_data->stats:"No stats are set for this server"));
				break;
			case 'game':
				$gametype = "unknown gametype";
				if ( isset($rcon_data->gametype) )
					$gametype = Rcon_Communicator::gametype_name($rcon_data->gametype);
				$bot->say($cmd->channel,$this->out_prefix()."Playing \00310$gametype\017 on \00304{$rcon_data->map}\017");
				break;
				
				
		}
	}
}