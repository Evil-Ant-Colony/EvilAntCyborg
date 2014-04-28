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
		$bot->say($cmd->channel,"{$rcon->out_prefix}\00312*\xf ".Color::dp2irc($cmd->params[1]),-16);
		return true;
	}
}

class Irc2Rcon_RawSay extends RawCommandExecutor
{
	public $say_command;
	public $action_command;
	public $rcon;
	
	function __construct(Rcon $rcon, $say_command='_ircmessage %s ^7: %s',$action_command='_ircmessage "^4*^3 %s" ^7 %s')
	{
		$this->say_command=$say_command;
		$this->action_command = $action_command;
		$this->rcon = $rcon;
	}
	
	function convert($text)
	{
		return Color::irc2dp($text);
	}
	
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		$text = str_replace(array('\\','"'),array('\\\\','\"'),$cmd->param_string());
		if ( preg_match("{^\1ACTION ([^\1]*)\1$}", $text, $match) )
			$this->rcon->send(sprintf($this->action_command,$cmd->from,$this->convert($match[1])));
		else
			$this->rcon->send(sprintf($this->say_command,$cmd->from,$this->convert($text)));
	}
}

class Irc2Rcon_RawSay_EncodeText extends Irc2Rcon_RawSay
{
	public $target_encoding;
	
	function __construct(Rcon $rcon, $target_encoding='ASCII//TRANSLIT', 
		$say_command='_ircmessage %s ^7: %s',$action_command='_ircmessage "^4*^3 %s" ^7 %s')
	{
		parent::__construct($rcon,$say_command,$action_command);
		$this->target_encoding = $target_encoding;
	}
	
	function convert($text)
	{
		return Color::irc2dp(iconv('UTF-8', $this->target_encoding,$text));
	}
}


class Irc2Rcon_UserEvent extends Irc2Rcon_Executor
{
	function __construct(Rcon $rcon, $event, $message, $command='_ircmessage "^4*^3 %s" ^7 %s')
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


class Irc2Rcon_UserJoin extends Irc2Rcon_UserEvent
{
	function __construct(Rcon $rcon, $message="has joined", $command='_ircmessage "^4*^3 %s" ^7 %s')
	{
		parent::__construct($rcon,'JOIN',$message,$command);
	}
	
	
	function check(MelanoBotCommand $cmd,MelanoBot $bot,BotData $data)
	{
		return parent::check($cmd,$bot,$data) && $cmd->from != $bot->nick;
	}
}

class Irc2Rcon_UserKicked extends Irc2Rcon_Executor
{
	public $message;
	
	function __construct(Rcon $rcon, $message='_ircmessage "^4*^3 %s" ^7 has kicked ^3%s')
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
	function __construct(Rcon $rcon, $message='_ircmessage "^4*^3 %s" ^7 is now known as ^3%s')
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
		$bot->say($cmd->channel,"{$rcon->out_prefix}<$nick\017> on \00304{$rcon->data->map}\017: \00304!admin\017 $message");
		$admin_msg = "{$rcon->out_prefix}{$cmd->channel} (\00304{$rcon->data->map}\017) <$nick\017> $message";
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
		$msg = "{$rcon->out_prefix}\00304SERVER ERROR\017 on {$rcon->data->write_server}: (\00304{$rcon->data->map}\017) ".
			Color::dp2irc($cmd->params[1]);
		$bot->say($cmd->channel,$msg);
		foreach($rcon->bot_data->active_users_in_list($bot,$this->list) as $admin)
			$bot->say($admin->nick,$msg);
		return true;
	}
}