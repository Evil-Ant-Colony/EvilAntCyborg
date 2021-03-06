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

require_once("rcon/rcon.php");
require_once("irc/data-source.php");
require_once("irc/executors/abstract.php");
require_once("rcon/rcon-players.php");
require_once("irc/dispatcher.php");
require_once("rcon/executors/rcon2irc-core.php");
require_once("rcon/executors/irc2rcon-core.php");

/**
 * \brief A command received from rcon
 */
class Rcon_Command
{
	public $data;          ///< Raw data (1 line of rcon output)
	public $server;        ///< Server it was received from
	public $channel;       ///< IRC channel to send messages to
	public $params=array();///< Regular expression captures for the executor
	
	function __construct($data, Rcon_Server $server, $irc_channel)
	{
		$this->data = $data;
		$this->server = $server;
		$this->channel = $irc_channel;
	}
}

/**
 * \brief Connects a IRC channel and a Rcon instance
 */
class Rcon_Communicator extends BotCommandDispatcher implements ExternalCommunicator
{
	const WAITING_IRC = -2;         ///< Not yet connected to IRC
	const CHECKING_CONNECTION = -1; ///< Checking that the connection is working
	const CHECK_FAILED = -3;        ///< Check failed, will disconnect if it doesn't get back
	const DISCONNECTED = 0;         ///< Connection failed, not connected
	const CONNECTED = 1;            ///< Connection is fine
	
	public $channel;                 ///< IRC channel
	public $data;                    ///< Rcon-related data object
	private $rcon;                   ///< Rcon instance
	public $rcon_executors = array();///< Rcon command executors
	public $rcon_filters = array();  ///< Filter rcon commands
	public $poll_commands = array(); ///< Commands to be polled periodically to rcon (as a string)
	public $poll_interval = 60;      ///< Number of seconds between polls
	private $poll_time = -1;         ///< When the next polling shall occur
	private $connection_status;      ///< Status of the RCON connection, see the constants above
	private $cache = "";             ///< Cache the last line if it has been split between multiple packets
	public $bot_data = null;         ///< Hax :-(, used to access BotData without passing it as a parameter to the rcon executors
	public $out_prefix = "";         ///< Prefix the rcon executors should use for IRC output
	public $first_poll_time = 0;     ///< Delay the first polling by this many seconds
	public $connection_messages=true;///< Whether to show connect/disconnect messages
	public $suppress_output = false; ///< If true don't show Rcon command output
	
	/**
	 * \brief Convert a gametype string identifier to a human-readable name
	 */
	static function gametype_name($gametype_shortname)
	{
		static $gametype_names = array(	
			"as"  => "assault",
			"ca"  => "clan arena",
			"cq"  => "conquest",
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
	
	/**
	 * \brief Send a command over rcon
	 */
	function send($command)
	{
		$this->rcon->send($command);
		if ( !$this->suppress_output )
			Logger::log("dp","<",Color::dp2ansi($command),0);
	}
	
	function __construct($channel,Rcon $rcon,$prefix=null,$first_poll_time=0)
	{
		parent::__construct(array($channel),$prefix);
		$this->channel = $channel;
		$this->rcon = $rcon;
		$this->poll_commands []= "status 1";
		if ( $prefix )
			$this->out_prefix = "$prefix ";
		$this->first_poll_time = $first_poll_time;
		
		$this->data = new StdClass();
		$this->data->rcon = $this->rcon;
		$this->data->hostname = "{$this->rcon->read}";
		$this->data->map = "unknown map";
	}
	
	/**
	 * \brief Setup the rcon connection and Rcon Data
	 */
	function initialize(BotData $data)
	{
		$this->connection_status = self::WAITING_IRC;
		$this->rcon->connect();
		$this->setup_server();
		if ( !isset($data->rcon) )
			$data->rcon = array();
		
		if ( ! isset($data->rcon["{$this->rcon->read}"]) )
		{
			$this->data->player = new PlayerManager;
			$data->rcon["{$this->rcon->read}"] = $this->data;
		}
		else
			$this->data = $data->rcon["{$this->rcon->read}"];
	}
	
	function is_connected()
	{
		return $this->connection_status == self::CONNECTED || $this->connection_status == self::CHECKING_CONNECTION;
	}
	
	/**
	 * \brief Set the connection status to the given value and send messages to IRC when needed
	 */
	private function set_connection_status($status,MelanoBot $bot)
	{
		if ( $status != $this->connection_status )
		{
			if ( $status == self::DISCONNECTED && $this->connection_messages )
			{
				$bot->say($this->channel,"{$this->out_prefix}\2Warning!\xf server \00304{$this->data->hostname}\xf disconnected!",16);
			}
			else if ( $status == self::CONNECTED && 
				$this->connection_status != self::CHECKING_CONNECTION && 
				$this->connection_status != self::CHECK_FAILED )
			{
				if ($this->connection_messages)
					$bot->say($this->channel,"{$this->out_prefix}Server \00309{$this->data->hostname}\xf connected.",16);
				$this->setup_server();
			}
		}
		$this->connection_status = $status;
	}

	/**
	 * \brief Don't keep junk on log_dest_udp
	 */
	function finalize(BotData $data)
	{
		$this->send("removefromlist log_dest_udp {$this->rcon->read}");
	}
	
	/**
	 * \brief Read from rcon end execute commands
	 */
	function step(MelanoBot $bot, BotData $data)
	{
		$this->bot_data = $data;
		
		if ( $this->connection_status == self::WAITING_IRC && 
			 $bot->connection_status() == MelanoBot::PROTOCOL_CONNECTED &&
			 $this->poll_time == -1 )
		{
			$this->connection_status = self::DISCONNECTED;
			$this->poll_time = time()+$this->first_poll_time;
		}
		
		$time = time();
		if ( $time > $this->poll_time && $this->poll_time != -1 )
		{
			if ( $this->connection_status != self::WAITING_IRC )
			{
				if ( $this->connection_status == self::CONNECTED )
					$this->set_connection_status(self::CHECKING_CONNECTION,$bot);
				else if ( $this->connection_status == self::CHECKING_CONNECTION )
					$this->set_connection_status(self::CHECK_FAILED,$bot);
				else
					$this->set_connection_status(self::DISCONNECTED,$bot);
			}
			
			// ensure we are always listening correctly
			$this->setup_server(false);
			
			foreach($this->poll_commands as $pc )
				$this->send($pc);
				
			$this->send("echo :melanorcon:ok");
			$this->poll_time = $time + $this->poll_interval;
		}
		
		$packet = $this->rcon->read();
		
		if ( !$packet->valid || !$packet->payload )
			return;
			
		if ( $this->cache )
		{
			$packet->payload = $this->cache.$packet->payload;
		}
		
 		$lines = explode ("\n",$packet->payload);

		if ( count($packet->payload) > 0 )
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
					else
						Logger::log("dp","!","Unmatched player line: ".Color::dp2ansi($lines[$i]),0);
				}
				$this->data->player->set_players($players);
			}
		}
		else if (  preg_match("{^:melanorcon:ok}",$packet->payload) && $this->connection_status != self::WAITING_IRC )
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
					if ( !$this->suppress_output )
						Logger::log("dp",">",Color::dp2ansi($line),0);
				
					foreach($this->rcon_executors as $executor)
						if ( $executor->step($cmd, $bot, $this) )
							break;
				}
				else if ( !$this->suppress_output && Logger::instance()->verbosity >= 5 )
				{
					Logger::log("dp",">","\x1b[31m".Color::dp2none($line)."\x1b[0m",5);
				}
			}
		}
		
		$this->bot_data = null;
	}
	
	/**
	 * \brief Filter out unwanted rcon commands
	 */
	function rcon_filter(Rcon_Command $cmd)
	{
		foreach ( $this->rcon_filters as $f )
			if ( !$f->filter($cmd,$this) )
				return false;
		return true;
	}
	
	/**
	 * \brief Ensure the rcon server s properly configured to retrieve useful information
	 */
	private function setup_server($log=true)
	{
		if ( $log )
			Logger::log("dp","!","Connecting to {$this->rcon->read}",1);
		$this->send("sv_cmd addtolist log_dest_udp {$this->rcon->read}");
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
	
	/**
	 * \brief Restore old sv_adminnick
	 * \sa set_sv_adminnick()
	 * \todo maybe it can be used a s a non-static function?
	 */
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
	
	/**
	 * \brief Set a temporary sv_adminnick
	 * \sa restore_sv_adminnick()
	 */
	static function set_sv_adminnick($rcon_data, $irc_nick)
	{
	
		if ( !isset($rcon_data->cvar["sv_adminnick"]) )
			$rcon_data->cvar["sv_adminnick"] = "";
		if ( !isset($rcon_data->sv_adminnick_vote_restore) )
			$rcon_data->sv_adminnick_vote_restore = $rcon_data->cvar["sv_adminnick"];
		$rcon_data->cvar["sv_adminnick"] = $irc_nick;
		$rcon_data->rcon->send("sv_adminnick \"$irc_nick\"");
	}
	
	
	function install($executors)
	{
		if ( !is_array($executors) )
			$executors = array($executors);
		
		foreach($executors as $ex)
		{
			$ex->comm = $this;
			$ex->install_on($this);
		}
	}
}


class Rcon_Multicast extends BotCommandDispatcher
{
	public $communicators = array();
	
	function __construct($channel,$communicators,$prefix)
	{
		parent::__construct(array($channel),$prefix);
		$this->channel = $channel;
		$this->communicators = $communicators;
	}
	
	function loop_step(MelanoBotCommand $cmd,MelanoBot $bot,BotData $data)
	{
		if ( !$this->matches($cmd) )
			return false;
		$cmd = $this->convert($cmd);
		$got_it = false;
		foreach($this->communicators as $comm)
		{
			$cc = clone $cmd;
			if ( $cmd->cmd == null )
				array_unshift($cc->params,$comm->prefix);
			else
			{
				array_unshift($cc->params,$cmd->cmd);
				$cc->cmd = $comm->prefix;
			}
			$got_it = $comm->loop_step($cc,$bot,$data) || $got_it;
		}
		return $got_it;
	}
}