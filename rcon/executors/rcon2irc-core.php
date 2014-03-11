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
			'%ip%'    => $player->ip,
			'%slot%'  => $player->slot,
			'%count%' => $rcon->data->player->count,
			'%max%'   => $rcon->data->player->max,
			'%map%'   => $rcon->data->map,
		);
		$bot->say($channel,str_replace(array_keys($values),array_values($values),$this->format));
	}
}

class Rcon2Irc_Join extends Rcon2Irc_JoinPart_Base
{
	
	function __construct($format="\00309+ join\xf: %name% \00304%map%\xf [\00304%count%\xf/\00304%max%\xf]")
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
class Rcon2Irc_Part extends Rcon2Irc_JoinPart_Base
{
	
	function __construct($format="\00304- part\xf: %name% \00304%map%\xf [\00304%count%\xf/\00304%max%\xf]")
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
		if ( $this->matches($cmd->params,1) )
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
		}
		else if ( $this->matches($cmd->params,2) )
		{
			$this->gather_team($cmd,$rcon);
		}
		else if ( $this->matches($cmd->params,5) )
		{
			$this->gather_player($cmd,$rcon);
		}
		else if ( $this->matches($cmd->params,11) )
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
		return $a->frags == $b->frags ? 0 : ( $a->frags > $b->frags ? -1 : +1 );
	}
	
	private function print_scores(Rcon_Command $cmd, MelanoBot $bot)
	{
		if ( empty($this->player_scores) )
			return;
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
	
	private function matches($array,$n)
	{
		return isset($array[$n]) && $array[$n];
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


class Rcon2Irc_MatchStart extends Rcon2Irc_Executor
{
	
	function __construct()
	{
		parent::__construct("{^:gamestart:([a-z]+)_(.*):[0-9.]*}");
	}
	
	function execute(Rcon_Command $cmd, MelanoBot $bot, Rcon_Communicator $rcon)
	{
		$rcon->data->gametype=$cmd->params[1];
		
		$bot->say($cmd->channel,"Playing \00310".$rcon->gametype_name($cmd->params[1]).
			"\xf on \00304{$cmd->params[2]}\xf (".
			($rcon->data->player->max-$rcon->data->player->count).
			" free slots); join now: \2xonotic +connect {$rcon->write_server}" );
		return true;
	}
}