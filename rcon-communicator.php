<?php

require_once("rcon.php");
require_once("data-source.php");
require_once("executors-abstract.php");
require_once("rcon-players.php");

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

abstract class Rcon2Irc_Executor
{
	public $regex;
	
	function Rcon2Irc_Executor($regex)
	{
		$this->regex = $regex;
	}
	
	/**
	 * \return True if you want to prevent further processing
	 */
	abstract function execute(Rcon_Command $cmd, MelanoBot $bot, BotData $data, $rcon_data);
	
	function step(Rcon_Command $cmd, MelanoBot $bot, BotData $data, $rcon_data)
	{
		if ( preg_match($this->regex,$cmd->data, $cmd->params) )
			return $this->execute($cmd,$bot,$data,$rcon_data);
		return false;
	}
	
	function install_on(Rcon_Communicator $comm)
	{
		$comm->rcon_executors []= $this;
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
			
			// unknown command hax to call setup_server, use Data to notify when coming online/offline
			
			foreach($this->poll_commands as $pc )
				$this->rcon->send($pc);
			$this->poll_time = $time + $this->poll_interval;
		}
		
		$packet = $this->rcon->read();
		
		if ( !$packet->valid )
			return;

		// update data status
		if ( preg_match("{host:\s+(.*)}",$packet->payload,$matches) )
		{
			$this->rcon_data->hostname =  $matches[1];
			$lines = explode ("\n",$packet->payload);
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
						print_r($player);
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
			$cmd = new Rcon_Command($packet->payload, $packet->server,$this->channel);
			foreach($this->rcon_executors as $executor)
				if ( $executor->step($cmd, $bot, $data, $this->rcon_data) )
					return;
		}
	}
	
	private function setup_server()
	{
		//$this->rcon->send("log_dest_udp {$this->rcon->read}");
		$this->rcon->send("sv_logscores_console 0");
		$this->rcon->send("sv_logscores_bots 1");
		$this->rcon->send("sv_eventlog 1");
		$this->rcon->send("sv_eventlog_console 1");
		$this->rcon->send('alias melanorcon_ircmessage "sv_cmd ircmsg \"$1\" \"$2-\""');
	}
}

abstract class Irc2Rcon_Executor extends CommandExecutor
{
	public $rcon;
	
	function Irc2Rcon_Executor(Rcon $rcon, $name,$auth=null,$synopsis="",$description="",$irc_cmd='PRIVMSG')
	{
		parent::__construct($name,$auth,$synopsis,$description,$irc_cmd);
		$this->rcon = $rcon;
	}
}


class Irc2Rcon_RawSay extends RawCommandExecutor
{
	public $say_command;
	public $action_command;
	public $rcon;
	
	function Irc2Rcon_RawSay(Rcon $rcon, $say_command='melanorcon_ircmessage %s ^7: %s',$action_command='melanorcon_ircmessage "^4*^3 %s" ^3 %s')
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


class Rcon2Irc_Say extends Rcon2Irc_Executor
{
	function Rcon2Irc_Say()
	{
		parent::__construct("{\1(.*?)\^7: (.*)}");
	}
	
	function execute(Rcon_Command $cmd, MelanoBot $bot, BotData $data, $rcon_data)
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
	
	function Rcon2Irc_JoinPart_Base($regex,$format)
	{
		parent::__construct($regex);
		$this->format = $format;
	}
	
	/// \todo show only non-bots in total
	protected function send_message($bot,$channel,$player,$rcon_data)
	{
		if ( !$player || $player->is_bot() )
			return;
		$values = array(
			'%name%'   => Color::dp2irc($player->name),
			'%ip%'    => $player->ip,
			'%slot%'  => $player->slot,
			'%count%' => $rcon_data->player->count,
			'%max%'   => $rcon_data->player->max,
			'%map%'   => $rcon_data->map,
		);
		$bot->say($channel,str_replace(array_keys($values),array_values($values),$this->format));
	}
}

class Rcon2Irc_Join extends Rcon2Irc_JoinPart_Base
{
	
	function Rcon2Irc_Join($format="\00309+ join\xf: %name% \00304%map%\xf [\00304%count%\xf/\00304%max%\xf]")
	{
		parent::__construct("{:join:(\d+):(\d+):([^:]*):(.*)}", $format);
	}
	
	function execute(Rcon_Command $cmd, MelanoBot $bot, BotData $data, $rcon_data)
	{
		$player = new RconPlayer();
		list ($player->id, $player->slot, $player->ip, $player->name) = array_splice($cmd->params,1);
		$rcon_data->player->add($player);
		
		$this->send_message($bot,$cmd->channel,$player,$rcon_data);
		
		return true;
	}
}
class Rcon2Irc_Part extends Rcon2Irc_JoinPart_Base
{
	
	function Rcon2Irc_Part($format="\00304- part\xf: %name% \00304%map%\xf [\00304%count%\xf/\00304%max%\xf]")
	{
		parent::__construct("{:part:(\d+)}",$format);
	}
	
	
	function execute(Rcon_Command $cmd, MelanoBot $bot, BotData $data, $rcon_data)
	{
		$player = $rcon_data->player->find_by_id($cmd->params[1]);
		if ( $player && !$player->is_bot() )
		{
			$rcon_data->player->remove($player->slot);
			
			$this->send_message($bot,$cmd->channel,$player,$rcon_data);
		}
		return true;
	}
}