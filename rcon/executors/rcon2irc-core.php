<?php

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
		$bot->say($cmd->channel,"<$nick\xf> $text");
		return true;
	}
}

abstract class Rcon2Irc_JoinPart_Base extends Rcon2Irc_Executor
{
	public $format;
	
	function __construct($regex,$format)
	{
		parent::__construct($regex);
		$this->format = $format;
	}
	
	/// \todo show only non-bots in total
	protected function send_message(MelanoBot $bot,$channel,$player,Rcon_Communicator $rcon)
	{
		if ( !$player || $player->is_bot() )
			return;
		$values = array(
			'%name%'   => Color::dp2irc($player->name),
			'%ip%'     => $player->ip,
			'%slot%'   => $player->slot,
			'%players%'=> $rcon->data->player->count_players(),
			'%bots%'   => $rcon->data->player->count_bots(),
			'%total%'  => $rcon->data->player->count_all(),
			'%max%'    => $rcon->data->player->max,
			'%map%'    => $rcon->data->map,
			'%country%'=> $player->country(),
		);
		$bot->say($channel,str_replace(array_keys($values),array_values($values),$this->format));
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
		$rcon->data->player->add($player);
		
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
	private $player_scores= array();
	private $team_scores  = array();
	public $team_colors = array(5 => Color::RED, 
								14 => Color::BLUE, 
								13 => Color::YELLOW, 
								10 => Color::MAGENTA );
	
	function __construct()
	{
		$re=array("(:end)",// 1
				  "(:teamscores:see-labels:(-?\d+)[-0-9,]*:(\d+))", // 2 - score=3 id=4
				  "(:player:see-labels:(-?\d+)[-0-9,]*:(\d+):([^:]+):(\d+):(.*))",// 5 - score=6 time=7 team=8 id=9 name=10
				  "(:scores:([a-z]+)_(.*)):",//11 gametype=12 map=13
				  );

		parent::__construct("{^".implode("|",$re)."}");
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
				$bot->say($cmd->channel,"$gametype\00304$map\017 ended:");
				$this->print_scores($cmd,$bot);
				$this->player_scores= array();
				$this->team_scores  = array();
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
		
		return false;
	}
	
	private function player_score( $player,$color)
	{
		$name = $player->name;
		if ( $color != null )
			$name = $color->irc().Color::dp2none($name)."\xf";
		else
			$name = Color::dp2irc($name);
		
		$score = $player->frags;
		if ( $player->team == 'spectator' )
			$score = "";
			
		return "\002".sprintf('%3s',$score)."\xf $name";
	}
	
	private function score_compare($a,$b)
	{
		if ( $a->team == 'spectator' && $b->team == 'spectator' ) return 0;
		if ( $a->team == 'spectator' ) return 1;
		if ( $b->team == 'spectator' ) return -1;
		return $a->frags == $b->frags ? 0 : ( $a->frags > $b->frags ? -1 : +1 );
	}
	
	private function print_scores(Rcon_Command $cmd, MelanoBot $bot)
	{
		usort($this->player_scores,'Rcon2Irc_Score::score_compare');
		if ( empty($this->team_scores) )
		{
			foreach($this->player_scores as $p )
				$bot->say($cmd->channel,$this->player_score($p,null));
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
			$ts['spectator'] = null;
			foreach(array_keys($ts) as $team )
			{
				$color = isset($this->team_colors[$team]) ? new Color($this->team_colors[$team],true) : null;
				foreach($this->player_scores as $p )
				{
					if ( $p->team == $team )
						$bot->say($cmd->channel,$this->player_score($p,$color));
				}
			}
		}
	}
	
	private function gather_team(Rcon_Command $cmd, Rcon_Communicator $rcon)
	{
		$this->team_scores[ $cmd->params[4] ] = $cmd->params[3];
	}
	
	private function gather_player(Rcon_Command $cmd, Rcon_Communicator $rcon)
	{
		$player = new RconPlayer;
		list ($player->frags, $player->time, $player->team, $player->id, $player->name) = array_splice($cmd->params,6);
		if ( $existing_player = $rcon->data->player->find_by_id($player->id) )
		{
			$existing_player->merge($player);
		}
		$this->player_scores []= $player;
	}
}

/**
 * \brief Notify that a new match has started 
 */
class Rcon2Irc_MatchStart extends Rcon2Irc_Executor
{
	
	function __construct()
	{
		parent::__construct("{^:gamestart:([a-z]+)_(.*):[0-9.]*}");
	}
	
	function execute(Rcon_Command $cmd, MelanoBot $bot, Rcon_Communicator $rcon)
	{
		if ( $rcon->data->player->count_players() < 1 )
			return false;
			
		$rcon->data->gametype=$cmd->params[1];
		$rcon->data->map = $cmd->params[2];
		
		$bot->say($cmd->channel,"Playing \00310".$rcon->gametype_name($cmd->params[1]).
			"\xf on \00304{$cmd->params[2]}\xf (".
			($rcon->data->player->max-$rcon->data->player->count_players()).
			" free slots); join now: \2xonotic +connect {$rcon->write_server}" );
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
		$p="\00312*\xf";
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