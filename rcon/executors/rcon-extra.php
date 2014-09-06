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
require_once('irc/executors/webapi.php');

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

class Irc2Rcon_RawSay extends Irc2Rcon_RawExecutor
{
	public $say_command;
	public $action_command;
	
	function __construct(Rcon $rcon, $say_command='_ircmessage %s ^7: %s',$action_command='_ircmessage "^4*^3 %s" ^7 %s')
	{
		parent::__construct($rcon);
		$this->say_command=$say_command;
		$this->action_command = $action_command;
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
	public $command, $include_bot;
	
	function __construct(Rcon $rcon, $event, $message, $include_bot = true,
		$command="_ircmessage \"^4*^3 %s\" ^7 %s" )
	{
		parent::__construct($rcon,null,null);
		$this->message = $message;
		$this->command = $command;
		$this->irc_cmd = $event;
	}
	
	function check(MelanoBotCommand $cmd,MelanoBot $bot,BotData $data)
	{
		return parent::check($cmd,$bot,$data) && 
			( $this->include_bot || $cmd->from != $bot->nick );
	}
	
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		$msg = str_replace('%message%',$cmd->param_string(),$this->message);
		
		$this->rcon->send(sprintf($this->command,$cmd->from,$msg));
	}
}

class Irc2Rcon_UserKicked extends Irc2Rcon_Executor
{
	public $message;
	
	function __construct(Rcon $rcon, $message='_ircmessage "^4*^3 %s" ^7 has kicked ^3%s^7 (%s)')
	{
		parent::__construct($rcon,null,null);
		$this->message=$message;
		$this->irc_cmd = "KICK";
	}
	
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		$this->rcon->send(sprintf($this->message,$cmd->from,$cmd->params[0],$cmd->params[1]));
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

/**
 * \brief Notify the admin with a private message when someone chats !admin
 */
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

/**
 * \brief Punish misbehaving players
 */
class Rcon2Irc_NotifyAdmin_Troll extends Rcon2Irc_Executor
{
    public $list;
    public $punishment;
    public $blacklisted_words = array();
    
    function __construct($blacklisted_words, 
            $punishment = 'r_trippy 1; v_psycho 1; defer 2 \"r_trippy 0\"; defer 10 \"v_psycho 0\"',
            $list='rcon-admin')
    {
        parent::__construct("{^\1(.*?)\^7:\s*!admin\s*(.*)}");
        $this->list =$list;
        $this->blacklisted_words = $blacklisted_words;
        $this->punishment = $punishment;
    }
    
    function execute(Rcon_Command $cmd, MelanoBot $bot, Rcon_Communicator $rcon)
    {
        $nick = Color::dp2irc($cmd->params[1]);
        $message = Color::dp2irc($cmd->params[2]);
        $punish = false;
        foreach($this->blacklisted_words as $word)
        {
            if ( strpos($message,$word) !== false )
            {
                $punish = true;
                break;
            }
        }
        if ( $punish )
        {
            $slot = null;
            foreach ( $rcon->data->player->all() as $player )
            {
                if ( $player->name == $cmd->params[1] )
                {
                    if ( $slot == null )
                        $slot = $player->slot;
                    else if ( $slot != $player->slot )
                        return true; // Can't find a unique player, so do nothing.
                }
            }
            $rcon->send("stuffto #$slot \"$punishment\"");
        }
        else
        {
            $bot->say($cmd->channel,"{$rcon->out_prefix}<$nick\017> on \00304{$rcon->data->map}\017: \00304!admin\017 $message");
            $admin_msg = "{$rcon->out_prefix}{$cmd->channel} (\00304{$rcon->data->map}\017) <$nick\017> $message";
            foreach($rcon->bot_data->active_users_in_list($bot,$this->list) as $admin)
                $bot->say($admin->nick,$admin_msg);
        }
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

class Rcon2Irc_Translate  extends Rcon2Irc_Executor
{
	function __construct()
	{
		// 1 = Player 2=[...] 3=from 4=to 5=text
		parent::__construct("{^\1(.*?)\^7: !translate\s*(\[([a-zA-Z]+)?-([a-zA-Z]+)?\])?\s*(.*)}");
	}
	
	function execute(Rcon_Command $cmd, MelanoBot $bot, Rcon_Communicator $rcon)
	{
		$sl="";
		$tl="en";
		
		
		if ( !empty($cmd->params[3]) )
		{
			$sl = GoogleTranslator::language_code($cmd->params[3]);
		}
		if ( !empty($cmd->params[4]) )
		{
			$tl = GoogleTranslator::language_code($cmd->params[4]);
			if ( !$tl ) $tl = "en";
		}
		
		$translated = GoogleTranslator::translate($sl,$tl,Color::dp2none($cmd->params[5]));
		
		if ( $translated )
		{
			Rcon_Communicator::set_sv_adminnick($rcon->data,"Server Translator");
			$translated = str_replace(array('\\','"'),array('\\\\','\"'),$translated);
			$rcon->send('say ^7'.$translated);
			Rcon_Communicator::restore_sv_adminnick($rcon->data);
		}
		
		
		
		return false;
	}
}

/**
 * \brief Autotranslate irc commands into rcon
 */
class Irc2Rcon_Autotranslate  extends Irc2Rcon_RawExecutor
{
	public $nicks = array();		///< IRC nicks for which translation is enabled
	public $target_language='en';	///< Target language code
	public $trigger;				///< Trigger for admin commands
	public $say_command;			///< Command used to send messages to rcon
	public $action_command;			///< Command used to send messages to rcon on ACTION
	public $target_encoding;		///< If not null convert the string encoding before sending it to rcon
	
	function __construct(Rcon $rcon, $say_command='say ^7', $action_command='say ^4* ^7', $trigger = 'autotranslate', $auth = 'rcon-admin', $target_encoding=null)
	{
		parent::__construct($rcon,$auth);
		$this->trigger = $trigger;
		$this->say_command = $say_command;
		$this->action_command = $action_command;
		$this->target_encoding = $target_encoding;
	}
	
	function check(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		return ( $cmd->cmd == $this->trigger && $this->check_auth($cmd->from,$cmd->host,$bot,$data) ) ||  
			( $cmd->cmd == null && in_array($cmd->from,$this->nicks) );
	}
	
	function convert($text)
	{
		if ( $this->target_encoding && $this->target_encoding != 'UTF-8' )
			$text = iconv('UTF-8', $this->target_encoding,$text);
		return Color::irc2dp($text);
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		if ( $cmd->cmd == $this->trigger )
		{
			switch ( $cmd->params[0] )
			{
				case 'clear':
					$this->nicks = array();
					$bot->say($cmd->channel, "Removed all automatic translations");
					break;
				case '+':
				case 'add':
					for ( $i = 1; $i < count($cmd->params); $i++ )
						if ( strlen($cmd->params[$i]) > 0 )
							$this->nicks[]= $cmd->params[$i];
					$this->nicks = array_unique($this->nicks);
					$bot->say($cmd->channel, "Autotranslations: ".implode(", ",$this->nicks));
					break;
				case '-':
				case 'rm':
					for ( $i = 1; $i < count($cmd->params); $i++ )
					{
						if ( ($key = array_search($cmd->params[$i], $this->nicks)) !== false )
							array_splice($this->nicks,$key,1);
					}
					$bot->say($cmd->channel, "Removed given automatic translations");
					break;
				case 'view':
					if ( empty($this->nicks) )
						$bot->say($cmd->channel,"No automatic translations");
					else
						$bot->say($cmd->channel,implode(", ",$this->nicks));
					break;
				default:
					$this->help($cmd, $bot, $data);
			}
			return true;
				
		}
		else
		{
			$text = Color::irc2none($cmd->param_string());
			$command = $this->say_command;
			if ( preg_match("{^\1ACTION ([^\1]*)\1$}", $text, $match) )
			{
				$text = $match[1];
				$command = $this->action_command;
			}
			$text = GoogleTranslator::translate("",$this->target_language,$text);
			if ( $text )
			{
				$rcon_data = $this->data($data);
				Rcon_Communicator::set_sv_adminnick($rcon_data,"[IRC] {$cmd->from}");
				$text = str_replace(array('\\','"'),array('\\\\','\"'),$text);
				$this->rcon->send(sprintf($command,$cmd->from).$this->convert($text));
				Rcon_Communicator::restore_sv_adminnick($rcon_data);
				return true;
			}
		}
		return false;
	}
	
	function name()
	{
		return $this->trigger;
	}
	
	function help(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		$bot->say($cmd->channel,"\x0304".$this->name()."\x03: \x0314{$this->trigger} clear|+ user...|- user...|view\x03");
		$bot->say($cmd->channel,"\x0302Manage automatic translations to rcon\x03");
	}
	
}
