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
	
	function Rcon_Command($data, Rcon_Server $server, $irc_channel)
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
	public $rcon;
	public $rcon_executors = array();
	public $rcon_filters = array();
	public $poll_commands = array();
	public $poll_interval = 30;
	private $poll_time = 0;
	private $rcon_data;
	private $connection_status;
	
	function Rcon_Communicator($channel,Rcon $rcon,$prefix=null)
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
		$this->rcon_data = $data->rcon["{$this->rcon->read}"];
	}
	
	private function set_connection_status($status,MelanoBot $bot)
	{
		if ( $status != $this->connection_status )
		{
			if ( $status == self::DISCONNECTED )
			{
				$bot->say($this->channel,"\2Warning!\xf server \00304{$this->rcon_data->hostname}\xf disconnected!");
			}
			else if ( $status == self::CONNECTED && $this->connection_status != self::CHECKING_CONNECTION )
			{
				$bot->say($this->channel,"Server \00309{$this->rcon_data->hostname}\xf connected.");
				$this->setup_server();
			}
		}
		$this->connection_status = $status;
	}
	
	function finalize(BotData $data)
	{
		$this->rcon->send("removefromlist log_dest_udp {$this->rcon->read}");
	}
	
	function install_rcon2irc($executors)
	{
		if ( !is_array($executors) )
			$this->rcon_executors[]=$executors;
		else
			$this->rcon_executors = array_merge($executors,$this->rcon_executors);
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
			$this->rcon->send("addtolist log_dest_udp {$this->rcon->read}");
			$this->rcon->send("echo :melanorcon:ok");
			
			foreach($this->poll_commands as $pc )
				$this->rcon->send($pc);
			$this->poll_time = $time + $this->poll_interval;
		}
		
		$packet = $this->rcon->read();
		
		if ( !$packet->valid || !$packet->payload )
			return;

		$lines = explode ("\n",$packet->payload);
		
		// update data status
		if ( preg_match("{host:\s+(.*)}",$packet->payload,$matches) )
		{
			$this->rcon_data->hostname =  $matches[1];
			$this->rcon_data->version = substr($lines[1],10);
			$this->rcon_data->protocol = substr($lines[2],10);
			$this->rcon_data->map = substr($lines[3],10);
			$this->rcon_data->timing = substr($lines[4],10);
			if ( preg_match("{players:  (\d+) active \((\d+) max\)}",$lines[5],$matches) )
			{
				$this->rcon_data->player->max = $matches[2];
				$this->rcon_data->player->count = $matches[1];
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
				$this->rcon_data->player->set_players($players);
			}
		}
		else if (  preg_match("{:melanorcon:ok}",$packet->payload) && $this->connection_status != self::WAITING_IRC )
		{
			Logger::log("dp","!","Server {$this->rcon->read} is connected", 3);
			$this->set_connection_status(self::CONNECTED,$bot);
		}

		// run commands
		if ( $this->connection_status != self::WAITING_IRC )
		{
			foreach ( $lines as $line ) 
			{
				Logger::log("dp",">",Color::dp2ansi($line),0);
				if ( $line )
				{
					$cmd = new Rcon_Command($line, $packet->server,$this->channel);
					foreach($this->rcon_executors as $executor)
						if ( $executor->step($cmd, $bot, $data, $this->rcon_data) )
							break;
				}
			}
		}
	}
	
	private function setup_server()
	{
		//$this->rcon->send("log_dest_udp {$this->rcon->read}");
		$this->rcon->send("sv_logscores_console 0");
		$this->rcon->send("sv_logscores_bots 1");
		$this->rcon->send("sv_eventlog 1");
		$this->rcon->send("sv_eventlog_console 1");
	}
}
