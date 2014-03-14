<?php

require_once("rcon/executors/rcon-abstract.php");

/**
 * \brief Handles say /me blah blah
 */
class Rcon2Irc_SayAction extends Rcon2Irc_Executor
{
	function __construct()
	{
		parent::__construct("{^\1\^4\* \^7(.*)}");
	}
	
	function execute(Rcon_Command $cmd, MelanoBot $bot, Rcon_Communicator $rcon)
	{
		$bot->say($cmd->channel,"\00312*\xf ".Color::dp2irc($cmd->params[1]));
		return true;
	}
}

class Irc2Rcon_RawSay extends RawCommandExecutor
{
	public $say_command;
	public $action_command;
	public $rcon;
	
	function __construct(Rcon $rcon, $say_command='_ircmessage %s ^7: %s',$action_command='_ircmessage "^4*^3 %s" ^3 %s')
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
class Irc2Rcon_UserEvent extends Irc2Rcon_Executor
{
	function __construct(Rcon $rcon, $event, $message, $command='_ircmessage "^4*^3 %s" ^3 %s')
	{
		parent::__construct($rcon,null,null);
		$this->command=$command;
		$this->message = $message;
		$this->irc_cmd = $event;
	}
	
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		$this->rcon->send(sprintf($this->command,$cmd->from,$this->message));
	}
}

class Irc2Rcon_UserKicked extends Irc2Rcon_Executor
{
	public $message;
	
	function __construct(Rcon $rcon, $message='_ircmessage "^4*^3 %s" ^3 has kicked %s')
	{
		parent::__construct($rcon,null,null);
		$this->message=$message;
		$this->irc_cmd = "KICK";
	}
	
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		$this->rcon->send(sprintf($this->message,$cmd->from,$cmd->params[0]));
	}
}

class Irc2Rcon_UserNick extends Irc2Rcon_UserKicked
{
	function __construct(Rcon $rcon, $message='_ircmessage "^4*^3 %s" ^3 is now known as %s')
	{
		parent::__construct($rcon,$message);
		$this->irc_cmd = "NICK";
	}
	
}



class Rcon2Irc_NotifyAdmin extends Rcon2Irc_Executor
{
	public $list;
	function __construct($list='rcon-admin')
	{
		parent::__construct("{^\1(.*?)\^7:\s*!admin\s*(.*)}");
		$this->list =$list;
	}
	
	function execute(Rcon_Command $cmd, MelanoBot $bot, Rcon_Communicator $rcon)
	{
		$nick = Color::dp2irc($cmd->params[1]);
		$message = Color::dp2irc($cmd->params[2]);
		$bot->say($cmd->channel,"<$nick\017> on \00304{$rcon->data->map}\017: \00304!admin\017 $message");
		$admin_msg = "{$cmd->channel} (\00304{$rcon->data->map}\017) <$nick\017> $message";
		foreach($rcon->bot_data->active_users_in_list($bot,$this->list) as $admin)
			$bot->say($admin->nick,$admin_msg);
		return true;
	}
}

class Rcon2Irc_HostError extends Rcon2Irc_Executor
{
	public $list;
	function __construct($list='rcon-admin')
	{
		parent::__construct("{^Host_Error:(.*)}");
		$this->list =$list;
	}
	
	function execute(Rcon_Command $cmd, MelanoBot $bot, Rcon_Communicator $rcon)
	{
		$msg = "\00304SERVER ERROR\017 on {$rcon->data->write_server}: (\00304{$rcon->data->map}\017) ".
			Color::dp2irc($cmd->params[1]);
		$bot->say($cmd->channel,$msg);
		foreach($rcon->bot_data->active_users_in_list($bot,$this->list) as $admin)
			$bot->say($admin->nick,$msg);
		return true;
	}
}