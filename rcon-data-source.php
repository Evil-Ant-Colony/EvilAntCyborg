<?php

require_once("rcon.php");
require_once("data-source.php");
require_once("executors-abstract.php");

class Rcon_Data_Source extends DataSource
{
	public $rcon;
	
	function Rcon_Data_Source( $rcon )
	{
		$this->rcon = $rcon;
	}
	
	
	function initialize(BotData $data)
	{
		$this->rcon->connect();
		$data->add_to_list('rcon',':RCON:',':RCON:');
	}
	
	
	function get_command()
	{
		$packet = $this->rcon->read();
		if ( !$packet->valid )
			return null;
		if ( preg_match("{\001(.*?)\^7: (.*)}",$packet->payload, $matches) )
		{
			$nick = Color::dp2irc($matches[1]);
			$text = Color::dp2irc($matches[2]);
			return new MelanoBotCommand('rcon_say',array("<$nick>", $text),':RCON:',':RCON:',
				$this->rcon->irc_name(),$packet->payload,'PRIVMSG');
		}
	}
}


/// \todo find some way to reconnect $rcon when the server restarts (or just keep spamming)
class RconDispatcher extends BotCommandDispatcher
{
	public $out_channel;
	public $rcon;
	
	function RconDispatcher($channel,$rcon,$prefix=null)
	{
		parent::__construct(array($channel,$rcon->irc_name()),$prefix);
		$this->out_channel = $channel;
		$this->rcon = $rcon;
	}
	
	
	function convert(MelanoBotCommand $cmd)
	{
		$cmd = parent::convert($cmd);
		if ( $this->matches($cmd) && $cmd->channel == $this->rcon->irc_name() )
			$cmd->channel = $this->out_channel;
		return $cmd;
	}
}