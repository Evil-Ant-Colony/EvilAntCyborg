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

require_once("rcon/executors/rcon-abstract.php");

/**
 * \brief Send chat messages from rcon to IRC
 * 
 * \bug If a player has the string "^7: " in their nick it won't be shown correctlt
 * This can be fixed by checking all the player names with the beginning of the string (passed \1)
 * But maybe it's not worth doing
 */
class Rcon2Irc_Say extends Rcon2Irc_Executor
{
	function __construct()
	{
		parent::__construct("{^\1(.*?)\^7: (.*)}");
	}
	
	function execute(Rcon_Command $cmd, MelanoBot $bot, Rcon_Communicator $rcon)
	{
		$nick = Color::dp2irc($cmd->params[1]);
		$text = Color::dp2irc($cmd->params[2]);
		$bot->say($cmd->channel,"{$rcon->out_prefix}<$nick\xf> $text",-16);
		return true;
	}
}

/**
 *
 * Format variables:
 *   - %name%     player name (colored)
 *   - %ip%       player IP address
 *   - %slot%     player slot
 *   - %country%  player country
 *   - %players%  number of connected players (not bots)
 *   - %bots%     number of bots
 *   - %total%    number of clients (players+bots)
 *   - %max%      maximum number of players
 *   - %free%     free slots
 *   - %gametype% long gametype name (eg: deathmatch)
 *   - %gt%       short gametype name (eg: dm)
 *   - %sv_host%  server host name
 *   - %sv_ip%    server IP address
 */
abstract class Rcon2Irc_JoinPart_Base extends Rcon2Irc_Executor
{
	public $format;
	
	function __construct($regex,$format)
	{
		parent::__construct($regex);
		$this->format = $format;
	}
	
	protected function send_message(MelanoBot $bot,$channel,$player,Rcon_Communicator $rcon)
	{
		if ( !$player || $player->is_bot() )
			return;
		$gametype = isset($rcon->data->gametype) ? $rcon->data->gametype : "";
		$values = array(
			'%name%'    => Color::dp2irc($player->name),
			'%ip%'      => $player->ip,
			'%slot%'    => $player->slot,
			'%players%' => $rcon->data->player->count_players(),
			'%bots%'    => $rcon->data->player->count_bots(),
			'%total%'   => $rcon->data->player->count_all(),
			'%max%'     => $rcon->data->player->max,
			'%free%'    => ($rcon->data->player->max-$rcon->data->player->count_players()),
			'%map%'     => $rcon->data->map,
			'%country%' => $player->country(),
			'%gametype%'=> $rcon->gametype_name($gametype),
			'%gt%'      => $gametype,
			'%sv_host%' => $rcon->data->hostname,
			'%sv_ip%'   => $rcon->write_server,
		);
		$bot->say($channel,$rcon->out_prefix.
			str_replace(array_keys($values),array_values($values),$this->format),1);
	}
}

/**
 * \brief Show +join messages
 */
class Rcon2Irc_Join extends Rcon2Irc_JoinPart_Base
{
	
	function __construct($format="\00309+ join\xf: %name% \00304%map%\xf [\00304%players%\xf/\00304%max%\xf]")
	{
		parent::__construct("{^:join:(\d+):(\d+):([^:]*):(.*)}", $format);
	}
	
	function execute(Rcon_Command $cmd, MelanoBot $bot, Rcon_Communicator $rcon)
	{
		$player = new RconPlayer();
		list ($player->id, $player->slot, $player->ip, $player->name) = array_splice($cmd->params,1);
		
		if ( strpos($player->ip,".") === false && preg_match("{([[:xdigit:]:]*)(.*)}",$player->name,$matches) && !empty($matches[2]) )
		{
			$player->ip .= $matches[1];
			$player->name .= $matches[2];
		}
			
		
		$already = $rcon->data->player->find($player->slot);
		$already = $already != null && $already->name == $player->name;
		
		$rcon->data->player->add($player);
		
		if ( !$already || $already->id != $player->id )
			$this->send_message($bot,$cmd->channel,$player,$rcon);
		
		return true;
	}
}
/**
 * \brief Show -part messages
 */
class Rcon2Irc_Part extends Rcon2Irc_JoinPart_Base
{
	
	function __construct($format="\00304- part\xf: %name% \00304%map%\xf [\00304%players%\xf/\00304%max%\xf]")
	{
		parent::__construct("{^:part:(\d+)}",$format);
	}
	
	
	function execute(Rcon_Command $cmd, MelanoBot $bot, Rcon_Communicator $rcon)
	{
		$player = $rcon->data->player->find_by_id($cmd->params[1]);
		if ( $player && !$player->is_bot() )
		{
			$rcon->data->player->remove($player->slot);
			
			$this->send_message($bot,$cmd->channel,$player,$rcon);
		}
		return true;
	}
}

/**
 * \brief Remove most of the useless Xonotic garbage log
 */
class Rcon2Irc_Filter_BlahBlah extends Rcon2Irc_Filter
{
	public $stuff = array(
		"^Map .* supports unknown game type .*",
		"^LHNET_Write: sendto returned error: Network is unreachable",
		"^Invalid sound info line: .*",
		'^[-_./a-zA-Z0-9]+ parsing warning: unknown surfaceparm "[a-zA-Z]+"',
		"^waypoint_load_links: couldn't find .*",
		"^WARNING: weapon model .*",
		"^Shader '.*' already defined.*",
		"^PRVM_LoadProgs: no cvar for autocvar global .*",
		"^plane [-0-9. ]* mismatches dist .*",
		"^Couldn't select .*",
		"^SHUFFLE: insert pos .*",
		"^Map .* contains the legacy 'type' keyword.*",
		"^Map .* provides unknown info item .*",
		"^Mod_LoadQ3Shaders: .*",
		"^Unstuck player entity .*",
		"^WARNING: autogenerated mapinfo file .*",
	);
	
	
	function filter(Rcon_Command $cmd,Rcon_Communicator $rcon)
	{
		foreach($this->stuff as $r)
			if ( preg_match("{{$r}}",$cmd->data) )
				return false;
		return !preg_match("{server received rcon command from {$rcon->read_server}:.*}",$cmd->data);
	}
}


/**
 * \brief Show score table at the end of each match
 */
class Rcon2Irc_Score extends Rcon2Irc_Executor
{
	protected $player_scores= array();
	protected $spectators   = array();
	protected $team_scores  = array();
	public $team_colors = array(5 => Color::RED, 
								14 => Color::BLUE, 
								13 => Color::YELLOW, 
								10 => Color::MAGENTA );
	protected $lms = false;
	protected $sort_reverse = false;
	public $show_spectators;
	
	function __construct($show_spectators = true)
	{
		$re=array("(:end)",// 1
				  "(:teamscores:see-labels:(-?\d+)[-0-9,]*:(\d+))", // 2 - score=3 id=4
				  "(:player:see-labels:(-?\d+)[-0-9,]*:(\d+):([^:]+):(\d+):(.*))",// 5 - score=6 time=7 team=8 id=9 name=10
				  "(:scores:([a-z]+)_(.*)):",//11 gametype=12 map=13
				  "(:labels:player:([^[,<!]*)(<?)!!,.*)",//14 primary=15 sort=16
				  );

		parent::__construct("{^".implode("|",$re)."}");
		
		$this->show_spectators = $show_spectators;
	}
	
	function execute(Rcon_Command $cmd, MelanoBot $bot,  Rcon_Communicator $rcon)
	{
		if ( !empty($cmd->params[1]) )
		{
			if ( !empty($this->player_scores) )
			{
				$gametype = "";
				if ( isset($rcon->data->gametype) )
					$gametype = "\00310".$rcon->gametype_name($rcon->data->gametype)."\xf on ";
				$map = isset($rcon->data->map) && $rcon->data->map ? $rcon->data->map : "?";
				
				$show_scores = $this->show_spectators || 
					($rcon->data->player->count_players()-count($this->spectators) > 0);
				$bot->say($cmd->channel,"{$rcon->out_prefix}$gametype\00304$map\017 ended".
					($show_scores?":":""));
				if ($show_scores)
					$this->print_scores($cmd,$bot);
				$this->player_scores= array();
				$this->spectators   = array();
				$this->team_scores  = array();
				$this->lms = false;
				$this->sort_reverse = false;
				$rcon->data->gametype=null;
				return true;
			}
		}
		else if ( !empty($cmd->params[2]) )
		{
			$this->gather_team($cmd,$rcon);
		}
		else if ( !empty($cmd->params[5]) )
		{
			$this->gather_player($cmd,$rcon);
		}
		else if ( !empty($cmd->params[11]) )
		{
			$rcon->data->map = $cmd->params[13];
			$rcon->data->gametype = $cmd->params[12];
		}
		else if ( !empty($cmd->params[14]) )
		{
			$this->seen_labels($cmd);
		}
		
		return false;
	}
	
	protected function seen_labels(Rcon_Command $cmd)
	{
		if ( $cmd->params[15] == "rank" )
			$this->lms = true;
		if ( $cmd->params[16] == "<" )
			$this->sort_reverse = true;
	}
	
	protected function player_score( $player,$color)
	{
		$name = $player->name;
		if ( $color != null )
			$name = $color->irc().Color::dp2none($name)."\xf";
		else
			$name = Color::dp2irc($name);
		
		$score = $player->frags;
			
		return "\002".sprintf('%3s',$score)."\xf $name";
	}
	
	protected function print_spectators(Rcon_Command $cmd, MelanoBot $bot)
	{
		if ( $this->show_spectators )
			foreach($this->spectators as $p )
				$bot->say($cmd->channel,"    ".Color::dp2irc($p->name),-1);
	}
	
	protected function score_compare($a,$b)
	{
		/*if ( $a->team == 'spectator' && $b->team == 'spectator' ) return 0;
		if ( $a->team == 'spectator' ) return 1;
		if ( $b->team == 'spectator' ) return -1;*/
		return $a->frags == $b->frags ? 0 : ( $a->frags > $b->frags ? -1 : +1 );
	}
	protected function sort_players()
	{
		usort($this->player_scores,'Rcon2Irc_Score::score_compare');
		if ( $this->sort_reverse )
			$this->player_scores = array_reverse($this->player_scores);
	}
	
	protected function print_scores(Rcon_Command $cmd, MelanoBot $bot)
	{
		$this->sort_players();
		if ( empty($this->team_scores) )
		{
			foreach($this->player_scores as $p )
				$bot->say($cmd->channel,$this->player_score($p,null),-1);
			$this->print_spectators($cmd,$bot);
		}
		else
		{
			$ts = array();
			foreach($this->team_scores as $team => $score )
			{
				$color = new Color($this->team_colors[$team],true);
				$ts[$team] = $color->irc()."$score\xf";
			}
			$bot->say($cmd->channel,"Team Scores: ".implode(":",array_values($ts)));
			foreach(array_keys($ts) as $team )
			{
				$color = isset($this->team_colors[$team]) ? new Color($this->team_colors[$team],true) : null;
				foreach($this->player_scores as $p )
				{
					if ( $p->team == $team )
						$bot->say($cmd->channel,$this->player_score($p,$color),-1);
				}
			}
			$this->print_spectators($cmd,$bot);
		}
	}
	
	protected function gather_team(Rcon_Command $cmd, Rcon_Communicator $rcon)
	{
		$this->team_scores[ $cmd->params[4] ] = $cmd->params[3];
	}
	
	protected function gather_player(Rcon_Command $cmd, Rcon_Communicator $rcon)
	{
		$player = new RconPlayer;
		list ($player->frags, $player->time, $player->team, $player->id, $player->name) = array_splice($cmd->params,6);
		$is_player = true;
		if ( $existing_player = $rcon->data->player->find_by_id($player->id) )
		{
			if ( $existing_player->is_bot() )
				$is_player = false;
			$existing_player->merge($player);
		}
		if ( $this->lms && $player->frags == 0 && $player->team == -1 )
			$player->team = "spectator";
			
		if ( $player->team == "spectator" )
		{
			$this->spectators []= $player;
		}
		else
		{
			$this->player_scores []= $player;
		}
	}
}

class Rcon2Irc_Score_Inline extends Rcon2Irc_Score
{
	
	function __construct($show_spectators = false)
	{
		parent::__construct($show_spectators);
	}
	
	protected function player_score( $player,$color)
	{
		$name = $player->name;
		if ( $color != null )
			$name = $color->irc().Color::dp2none($name)."\xf";
		else
			$name = Color::dp2irc($name);
		
		$score = $player->frags;
			
		return "\002$score\xf $name";
	}
	
	protected function print_spectators(Rcon_Command $cmd, MelanoBot $bot)
	{
		if ( $this->show_spectators )
		{
			$score_string = array();
			foreach($this->player_scores as $p )
				if ( $this->spectators )
					$score_string[] = Color::dp2irc($p->name);
			$bot->say($cmd->channel,implode(", ",$score_string),-1);
		}
	}
	
	protected function print_scores(Rcon_Command $cmd, MelanoBot $bot)
	{
		usort($this->player_scores,'Rcon2Irc_Score::score_compare');
		if ( empty($this->team_scores) )
		{
			$score_string = array();
			foreach($this->player_scores as $p )
				if ( $this->show_spectators || $p->team != 'spectator' )
					$score_string[] = $this->player_score($p,null);
			$bot->say($cmd->channel,implode(", ",$score_string),-1);
			$this->print_spectators($cmd,$bot);
		}
		else
		{
			$ts = array();
			foreach($this->team_scores as $team => $score )
			{
				$color = new Color($this->team_colors[$team],true);
				$ts[$team] = $color->irc()."$score\xf";
			}
			$bot->say($cmd->channel,"Team Scores: ".implode(":",array_values($ts)));
			foreach(array_keys($ts) as $team )
			{
				
				$color = isset($this->team_colors[$team]) ? new Color($this->team_colors[$team],true) : null;
				$score_string = array();
				foreach($this->player_scores as $p )
				{
					if ( $p->team == $team )
						$score_string[] = $this->player_score($p,$color);
				}
				$bot->say($cmd->channel,implode(", ",$score_string),-1);
			}
			$this->print_spectators($cmd,$bot);
		}
	}
}

/**
 * \brief Notify that a new match has started 
 *
 * Format variables:
 *   - %players%  number of connected players (not bots)
 *   - %bots%     number of bots
 *   - %total%    number of clients (players+bots)
 *   - %max%      maximum number of players
 *   - %free%     free slots
 *   - %gametype% long gametype name (eg: deathmatch)
 *   - %gt%       short gametype name (eg: dm)
 *   - %sv_host%  server host name
 *   - %sv_ip%    server IP address
 */
class Rcon2Irc_MatchStart extends Rcon2Irc_Executor
{
	public $message;
	
	function __construct($message = "Playing \00310%gametype%\xf on \00304%map%\xf (%free% free slots); join now: \2xonotic +connect %sv_ip%")
	{
		parent::__construct("{^:gamestart:([a-z]+)_(.*):[0-9.]*}");
		$this->message = $message;
	}
	
	protected function send_message(MelanoBot $bot,$channel,Rcon_Communicator $rcon)
	{
		$gametype = isset($rcon->data->gametype) ? $rcon->data->gametype : "";
		$values = array(
			'%players%' => $rcon->data->player->count_players(),
			'%bots%'    => $rcon->data->player->count_bots(),
			'%total%'   => $rcon->data->player->count_all(),
			'%max%'     => $rcon->data->player->max,
			'%free%'    => ($rcon->data->player->max-$rcon->data->player->count_players()),
			'%map%'     => $rcon->data->map,
			'%gametype%'=> $rcon->gametype_name($gametype),
			'%gt%'      => $gametype,
			'%sv_host%' => $rcon->data->hostname,
			'%sv_ip%'   => $rcon->write_server,
		);
		$bot->say($channel,$rcon->out_prefix.str_replace(array_keys($values),array_values($values),$this->message),-1);
	}
	
	function execute(Rcon_Command $cmd, MelanoBot $bot, Rcon_Communicator $rcon)
	{
		if ( $rcon->data->player->count_players() < 1 )
			return false;
			
		$rcon->data->gametype=$cmd->params[1];
		$rcon->data->map = $cmd->params[2];
		$this->send_message($bot,$cmd->channel,$rcon);
		return true;
	}
}

/**
 * \brief instead of polling regularly only poll on game start
 * \note Install before Rcon2Irc_MatchStart
 */
class Rcon2Irc_SlowPolling extends Rcon2Irc_Executor
{
	public $commands;
	
	function __construct($commands)
	{
		parent::__construct("{^:gamestart:.*}");
		$this->commands = $commands;
	}
	
	
	function execute(Rcon_Command $cmd, MelanoBot $bot, Rcon_Communicator $rcon)
	{
		foreach($this->commands as $pc )
			$rcon->send($pc);
		return false;
	}
}

/**
 * \brief Detect cvar changes and save them to data
 * \note  it won't work properly for cvars that have " in their value
 */
class Rcon2Irc_GetCvars extends Rcon2Irc_Executor
{	
	function __construct()
	{
		parent::__construct('{^"([^"]+)" is "([^"]*)"}');
	}
	
	
	function execute(Rcon_Command $cmd, MelanoBot $bot, Rcon_Communicator $rcon)
	{
		$rcon->data->cvar[$cmd->params[1]] = $cmd->params[2];
		return false;
	}
}

class Rcon2Irc_Votes extends Rcon2Irc_Executor
{	
	function __construct()
	{
		$re=array("(vcall:(\d+):(.*))",// 1 - userid=2 vote=3
					// 4 - result=5 yes=6 no=7 abstain=8 not=9 min=10
				  "(v(yes|no|timeout):(\d+):(\d+):(\d+):(\d+):(-?\d+))", 
				  "(vstop:(\d+))",   // 11 - userid=12
				  "(vlogin:(\d+))",  // 13 - userid=14
				  "(vdo:(\d+):(.*))",// 15 - userid=16 vote=17
				  );

		parent::__construct("{^:vote:".implode("|",$re)."}");
	}
	
	function id2nick($id,Rcon_Communicator $rcon)
	{
		$name = "(unknown)";
		if ( $id == 0 )
		{
			if ( !empty($rcon->data->cvar["sv_adminnick"]) )
				$name = $rcon->data->cvar["sv_adminnick"];
			else
				$name = "(server admin)";
		}
		else if ( $player = $rcon->data->player->find_by_id($id) )
			$name = Color::dp2irc($player->name);
		return $name;
	}
	
	function execute(Rcon_Command $cmd, MelanoBot $bot, Rcon_Communicator $rcon)
	{
		$p="{$rcon->out_prefix}\00312*\xf";
		if ( !empty($cmd->params[1]) )
		{
			$name = $this->id2nick($cmd->params[2],$rcon);
			$vote = Color::dp2irc($cmd->params[3]);
			$bot->say($cmd->channel,"$p $name calls a vote for $vote");
		}
		else if ( !empty($cmd->params[4]) )
		{
			list($result,$yes,$no,$abstain,$not,$min) = array_slice($cmd->params,5);
			$msg = "$p vote ";
			switch($result)
			{
				case "yes":     $msg.="\00303passed\xf"; break;
				case "no":      $msg.="\00304failed\xf"; break;
				case "timeout": $msg.="\00307timed out\xf"; break;
			}
			$msg .= ": \00303$yes\xf:\00304$no\xf";
			if ( $abstain+=$not ) $msg .= ", $abstain didn't vote";
			if ( $min > 0 ) $msg .= " ($min needed)";
			
			$bot->say($cmd->channel,$msg);
			
		}
		else if ( !empty($cmd->params[11]) )
		{
			$name = $this->id2nick($cmd->params[12],$rcon);
			$bot->say($cmd->channel,"$p $name stopped the vote");
		}
		else if ( !empty($cmd->params[13]) )
		{
			$name = $this->id2nick($cmd->params[14],$rcon);
			$bot->say($cmd->channel,"$p $name logged in as \00307master\xf");
		}
		else if ( !empty($cmd->params[15]) )
		{
			$name = $this->id2nick($cmd->params[16],$rcon);
			$bot->say($cmd->channel,"$p $name used their master status to do ".Color::dp2irc($cmd->params[17]));
		}
		Rcon_Communicator::restore_sv_adminnick($rcon->data);
		return true;
	}
}


class Rcon2Irc_UpdateBans extends Rcon2Irc_Executor
{	
	function __construct()
	{
		$re=array("(\^2Listing all existing active bans:)",// 1 
				  // 2 - banid=3 ip=4 time=5
				  "\s*(#([0-9]+): ([./0-9]+) is still banned for (inf|[0-9]+)(?:\.[0-9]+)? seconds)"
				  );

		parent::__construct("{^".implode("|",$re)."}");
	}
	
	
	function execute(Rcon_Command $cmd, MelanoBot $bot, Rcon_Communicator $rcon)
	{
		if ( !empty($cmd->params[1]) )
		{
			$rcon->data->bans = array();
			Logger::log("dp","!", "Ban list \x1b[31mcleared\x1b[0m",3);
		}
		else if ( !empty($cmd->params[2]) )
		{
			$b = new StdClass;
			$b->ip = $cmd->params[4];
			$b->time = $cmd->params[5];
			$rcon->data->bans[$cmd->params[3]] = $b;
			
			Logger::log("dp","!",
				"\x1b[32mAdding\x1b[0m ban #\x1b[31m{$cmd->params[3]}\x1b[0m: \x1b[36m$b->ip\x1b[0m",3);
		}
		return false;
	}
}


/**
 * \brief Show nick changes
 */
class Rcon2Irc_Name extends Rcon2Irc_Executor
{
	
	function __construct()
	{
		parent::__construct("{^:name:(\d+):(.*)}");
	}
	
	
	function execute(Rcon_Command $cmd, MelanoBot $bot, Rcon_Communicator $rcon)
	{
		$player = $rcon->data->player->find_by_id($cmd->params[1]);
		if ( $player && !$player->is_bot() )
		{
			$bot->say($cmd->channel,"{$rcon->out_prefix}\00312*\xf ".Color::dp2irc($player->name).
				" is now known as ".Color::dp2irc($cmd->params[2]),-16);
			$player->name = $cmd->params[2];
		}
		return true;
	}
}



/**
 * \brief Detect active mutators and save them to data
 */
class Rcon2Irc_GetMutators extends Rcon2Irc_Executor
{	
	function __construct()
	{
		parent::__construct('{^:gameinfo:mutators:LIST:(.*)}');
	}
	
	
	function execute(Rcon_Command $cmd, MelanoBot $bot, Rcon_Communicator $rcon)
	{
		$rcon->data->mutators = explode(":",$cmd->params[1]);
		Logger::log("dp","!","Mutators: \x1b[1m".
			implode("\x1b[22m, \x1b[1m",$rcon->data->mutators)."\x1b[0m");
		return false;
	}
}