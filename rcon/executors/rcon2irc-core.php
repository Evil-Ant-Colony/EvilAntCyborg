<?php

require_once("rcon/executors/rcon-abstract.php");


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
