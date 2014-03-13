<?php

require_once("rcon/rcon.php");
require_once("irc/data-source.php");
require_once("irc/executors/abstract.php");
require_once("rcon/rcon-players.php");
require_once("irc/dispatcher.php");
require_once("rcon/executors/rcon2irc-core.php");
require_once("rcon/executors/irc2rcon-core.php");

class Rcon_Command
{
	public $data;
	public $server;
	public $channel;
	public $params = array();
	
	function __construct($data, Rcon_Server $server, $irc_channel)
	{
		$this->data = $data;
		$this->server = $server;
		$this->channel = $irc_channel;
	}
}


class Rcon_Communicator extends BotCommandDispatcher implements ExternalCommunicator
{
	const WAITING_IRC = -2;
	const CHECKING_CONNECTION = -1;
	const DISCONNECTED = 0;
	const CONNECTED = 1;
	
	public $channel;
	public $data;
	private $rcon;
	public $rcon_executors = array();
	public $rcon_filters = array();
	public $poll_commands = array();
	public $poll_interval = 30;
	private $poll_time = 0;
	private $connection_status;
	private $cache = "";
	
	static function gametype_name($gametype_shortname)
	{
		static $gametype_names = array(	
			"as"  => "assault",
			"ca"  => "clan arena",
			"ctf" => "capture the flag",
			"cts" => "race cts",
			"dom" => "domination",
			"dm"  => "deathmatch",
			"ft"  => "freezetag",
			"inf" => "infection",
			"inv" => "invasion",
			"jb"  => "jailbreak",
			"ka"  => "keepaway",
			"kh"  => "key hunt",
			"lms" => "last man standing",
			"nb"  => "nexball",
			"ons" => "onslaught",
			"rc"  => "race",
			"tdm" => "team deathmatch",
		);
		return isset($gametype_names[$gametype_shortname])?$gametype_names[$gametype_shortname]:$gametype_shortname;
	}
	
	function send($command)
	{
		$this->rcon->send($command);
	}
	
	function __construct($channel,Rcon $rcon,$prefix=null)
	{
		parent::__construct(array($channel),$prefix);
		$this->channel = $channel;
		$this->rcon = $rcon;
		$this->poll_commands []= "status 1";
	}
	
	function initialize(BotData $data)
	{
		$this->connection_status = self::WAITING_IRC;
		$this->rcon->connect();
		$this->setup_server();
		if ( !isset($data->rcon) )
			$data->rcon = array();
		
		if ( ! isset($data->rcon["{$this->rcon->read}"]) )
		{
			$data->rcon["{$this->rcon->read}"] = new StdClass();
			$data->rcon["{$this->rcon->read}"]->rcon = $this->rcon;
			$data->rcon["{$this->rcon->read}"]->player = new PlayerManager;
			$data->rcon["{$this->rcon->read}"]->hostname = "{$this->rcon->read}";
		}
		$this->data = $data->rcon["{$this->rcon->read}"];
	}
	
	private function set_connection_status($status,MelanoBot $bot)
	{
		if ( $status != $this->connection_status )
		{
			if ( $status == self::DISCONNECTED )
			{
				$bot->say($this->channel,"\2Warning!\xf server \00304{$this->data->hostname}\xf disconnected!");
			}
			else if ( $status == self::CONNECTED && $this->connection_status != self::CHECKING_CONNECTION )
			{
				$bot->say($this->channel,"Server \00309{$this->data->hostname}\xf connected.");
				$this->setup_server();
			}
		}
		$this->connection_status = $status;
	}
	
	function finalize(BotData $data)
	{
		$this->send("removefromlist log_dest_udp {$this->rcon->read}");
	}
	
	function step(MelanoBot $bot, BotData $data)
	{
		if ( $this->connection_status == self::WAITING_IRC && $bot->connection_status() == MelanoBot::PROTOCOL_CONNECTED )
		{
			$this->connection_status = self::DISCONNECTED;
			$this->poll_time = 0;
		}
		
		$time = time();
		if ( $time > $this->poll_time )
		{
			if ( $this->connection_status != self::WAITING_IRC )
			{
				if ( $this->connection_status == self::CONNECTED )
					$this->set_connection_status(self::CHECKING_CONNECTION,$bot);
				else
					$this->set_connection_status(self::DISCONNECTED,$bot);
			}
				
			// ensure we are always listening correctly
			$this->send("addtolist log_dest_udp {$this->rcon->read}");
			$this->send("echo :melanorcon:ok");
			
			foreach($this->poll_commands as $pc )
				$this->send($pc);
			$this->poll_time = $time + $this->poll_interval;
		}
		
		$packet = $this->rcon->read();
		
		if ( !$packet->valid || !$packet->payload )
			return;
		
		if ( $this->cache )
		{
			$packet->payload = $this->cache.$packet->payload;
			$this->cache = "";
		}
		$lines = explode ("\n",$packet->payload);
		if ( strlen($packet->contents) >= Rcon_Packet::MAX_READ_LENGTH )
			$this->cache = array_pop($lines);
		
		// update data status
		if ( preg_match("{host:\s+(.*)}",$packet->payload,$matches) )
		{
			$this->data->hostname =  $matches[1];
			$this->data->version = substr($lines[1],10);
			$this->data->protocol = substr($lines[2],10);
			$this->data->map = substr($lines[3],10);
			$this->data->timing = substr($lines[4],10);
			if ( preg_match("{players:  (\d+) active \((\d+) max\)}",$lines[5],$matches) )
			{
				$this->data->player->max = $matches[2];
			}
			if (preg_match("{IP\s+%pl\s+ping\s+time\s+frags\s+no\s+name}",$lines[7]) )
			{
				$players = array();
				for ( $i = 8; $i < count($lines); $i++ )
				{
					//                         IP        %pl       ping      time      frags     no              name
					if ( preg_match("{\^[0-9]([^ ]+)\s+([^ ]+)\s+([^ ]+)\s+([^ ]+)\s+([^ ]+)\s+([^ ]+)\s+\^[0-9](.*)}",$lines[$i],$matches) )
					{
						$player = new RconPlayer();
						list ($player->ip, $player->pl, $player->ping, 
							$player->time, $player->frags, $player->slot, 
							$player->name) = array_splice($matches,1);
						$players[]=$player;
					}
				}
				$this->data->player->set_players($players);
			}
		}
		else if (  preg_match("{:melanorcon:ok}",$packet->payload) && $this->connection_status != self::WAITING_IRC )
		{
			Logger::log("dp","!","Server {$this->rcon->read} is connected",3);
			$this->set_connection_status(self::CONNECTED,$bot);
		}

		// run commands
		if ( $this->connection_status != self::WAITING_IRC )
		{
			foreach ( $lines as $line ) 
			{
				$cmd = new Rcon_Command($line, $packet->server,$this->channel);
				if ( $this->rcon_filter($cmd) )
				{
					Logger::log("dp",">",Color::dp2ansi($line),0);
				
					foreach($this->rcon_executors as $executor)
						if ( $executor->step($cmd, $bot, $this) )
							break;
				}
				else if ( Logger::instance()->verbosity >= 5 )
				{
					Logger::log("dp",">","\x1b[31m".Color::dp2none($line)."\x1b[0m",5);
				}
			}
		}
	}
	
	function rcon_filter(Rcon_Command $cmd)
	{
		foreach ( $this->rcon_filters as $f )
			if ( !$f->filter($cmd,$this) )
				return false;
		return true;
	}
	
	private function setup_server()
	{
		Logger::log("dp","!","Connecting to {$this->rcon->read}",1);
		//$this->rcon->send("log_dest_udp {$this->rcon->read}");
		$this->send("sv_logscores_console 0");
		$this->send("sv_logscores_bots 1");
		$this->send("sv_eventlog 1");
		$this->send("sv_eventlog_console 1");
	}
	
	function __get($name)
	{
		if ( $name == 'read_server' )
			return $this->rcon->read;
		else if ( $name == 'write_server' )
			return $this->rcon->write;
	}
	
	static function restore_sv_adminnick($rcon_data)
	{
		if ( isset($rcon_data->sv_adminnick_vote_restore) )
		{
			$nick = $rcon_data->sv_adminnick_vote_restore;
			unset($rcon_data->sv_adminnick_vote_restore);
			$rcon_data->cvar["sv_adminnick"] = $nick;
			$rcon_data->rcon->send("sv_adminnick \"$nick\"");
		}
	}
	
	static function set_sv_adminnick($rcon_data, $irc_nick)
	{
	
		if ( !isset($rcon_data->cvar["sv_adminnick"]) )
			$rcon_data->cvar["sv_adminnick"] = "";
		if ( !isset($rcon_data->sv_adminnick_vote_restore) )
			$rcon_data->sv_adminnick_vote_restore = $rcon_data->cvar["sv_adminnick"];
		$rcon_data->cvar["sv_adminnick"] = $irc_nick;
		$rcon_data->rcon->send("sv_adminnick \"$irc_nick\"");
	}
}
