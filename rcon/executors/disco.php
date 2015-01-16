<?php
/**
 * \file Prevent disco from playing and stuff
 * Define the function is_disco($player) to check if a player is disco
 */

/**
 * \brief Super disco mode
 */
class Rcon2Irc_AutoDisco extends Rcon2Irc_Executor
{	
	function __construct()
	{
		parent::__construct("{^:join:(\d+):(\d+):((?:[0-9]+(?:\.[0-9]+){3})|(?:[[:xdigit:]](?::[[:xdigit:]]){7})):(.*)}");
	}
	
	function execute(Rcon_Command $cmd, MelanoBot $bot, Rcon_Communicator $rcon)
	{
		$player = new RconPlayer();
		list ($player->id, $player->slot, $player->ip, $player->name) = array_splice($cmd->params,1);
		
		if ( is_disco($player) )
		{
			$bot->say($cmd->channel,$rcon->out_prefix.Color::dp2irc($player->name)." #{$player->slot} ({$player->ip}) is in the disco",1024);
			$rcon->send("discomode #{$player->slot}");
		}
		return false;
	}
}

class Rcon2Irc_AutoDisco_MatchStart extends Rcon2Irc_Executor
{
	function __construct()
	{
		parent::__construct("{(^:gamestart:([a-z]+)_(.*):[0-9.]*)|(^:startdelay_ended)}");
	}
	
	function execute(Rcon_Command $cmd, MelanoBot $bot, Rcon_Communicator $rcon)
	{
		foreach ( $rcon->data->player->all() as $player )
		{
			if ( is_disco($player) )
			{
				$bot->say($cmd->channel,$rcon->out_prefix.Color::dp2irc($player->name)." #{$player->slot} ({$player->ip}) is still in the disco",16);
				$rcon->send("discomode #{$player->slot}");
			}
		}
		return false;
	}
}

class Rcon2Irc_AutoDisco_Always extends Rcon2Irc_Executor
{
	function __construct()
	{
		parent::__construct("{^:melanorcon:ok}");
	}
	
	function execute(Rcon_Command $cmd, MelanoBot $bot, Rcon_Communicator $rcon)
	{
		foreach ( $rcon->data->player->all() as $player )
		{
			if ( is_disco($player) )
			{
				$rcon->send("discomode #{$player->slot}");
			}
		}
		return false;
	}
}

class Rcon2Irc_AutoDisco_NoVotes extends Rcon2Irc_Executor
{
	function __construct()
	{
		parent::__construct("{^:vote:vcall:(\d+):(.*)}");
	}
		
	function execute(Rcon_Command $cmd, MelanoBot $bot, Rcon_Communicator $rcon)
	{
		$player = $rcon->data->player->find_by_id($cmd->params[1]);
		if ( $player && is_disco($player) )
		{
			$bot->say($cmd->channel,$rcon->out_prefix."Auto-stopping ".Color::dp2irc($player->name)."'s vote",16);
			$rcon->send("vstop");
		}
		return false;
	}
}

abstract class Rcon2Irc_KickDisco_Base extends Rcon2Irc_Executor
{
	function __construct($regex) 
	{
		parent::__construct($regex);
	}
	
	function maybe_kick(RconPlayer $player, Rcon_Command $cmd, MelanoBot $bot, Rcon_Communicator $rcon) 
	{
		if ( is_disco($player) )
		{
			$bot->say($cmd->channel,$rcon->out_prefix.Color::dp2irc($player->name)." #{$player->slot} ({$player->ip}) has been kicked (Disco)",1024);
			$rcon->send("kickban #{$player->slot}");
			unset($player->is_disco);
		}
	}
}

class Rcon2Irc_KickDisco_Join extends Rcon2Irc_KickDisco_Base
{	
	function __construct()
	{
		parent::__construct("{^:(?:join|connect):(\d+):(\d+):((?:[0-9]+(?:\.[0-9]+){3})|(?:[[:xdigit:]](?::[[:xdigit:]]){7})):(.*)}");
	}
	
	function execute(Rcon_Command $cmd, MelanoBot $bot, Rcon_Communicator $rcon)
	{
		$player = new RconPlayer();
		list ($player->id, $player->slot, $player->ip, $player->name) = array_splice($cmd->params,1);
		$this->maybe_kick($player,$cmd,$bot,$rcon);
		return false;
	}
}

class Rcon2Irc_KickDisco_MatchStart extends Rcon2Irc_KickDisco_Base
{
	function __construct()
	{
		parent::__construct("{(^:gamestart:([a-z]+)_(.*):[0-9.]*)|(^:startdelay_ended)}");
	}
	
	function execute(Rcon_Command $cmd, MelanoBot $bot, Rcon_Communicator $rcon)
	{
		foreach ( $rcon->data->player->all() as $player )
		{
			$this->maybe_kick($player,$cmd,$bot,$rcon);
		}
		return false;
	}
}
