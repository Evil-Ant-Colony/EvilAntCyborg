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

require("misc/inflector.php");

class Executor_Echo extends CommandExecutor
{
	public $action;///< Either \c false or a 3rd person verb to trigger an action (eg: "does")
	
	function __construct($trigger,$action=false,$auth=null)
	{
		$this->action = $action;
		parent::__construct($trigger,$auth,"$trigger Text...","Make the bot $trigger something");
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		$text = $cmd->param_string();
		if ( $this->action )
			$text = irc_action("{$this->action} $text");
		$bot->say($cmd->channel,$text);
	}
}


class Executor_Action extends CommandExecutor
{
	
	function __construct($trigger="please",$auth=null)
	{
		parent::__construct($trigger,$auth,"$trigger Action...",'Make the bot perform a chat action (Roleplaying)');
		$this->reports_error = true;
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		if ( $this->check_auth($cmd->from,$cmd->host,$bot,$driver) && count($cmd->params)>0 )
		{
			global $english_verb;
			$ps = new PronounSwapper($cmd->from,$bot->nick);
			$action = $english_verb->inflect(array_shift($cmd->params));
			if ( $action == "doesn't" && count($cmd->params)>0 && $cmd->params[0] == 'be' )
			{
				$action = "isn't";
				array_shift($cmd->params);
			}
			
			for($i = 0; $i < count($cmd->params); $i++)
			{
				$word = $cmd->params[$i];
				$context_pre = $i > 0 ? $cmd->params[$i-1] : "";
				$context_post = $i < count($cmd->params)-1 ? $cmd->params[$i+1] : "";
				$action .= " ".$ps->inflect($word,$context_pre,$context_post);
			}
			$bot->say($cmd->channel,"\x01ACTION $action\x01");
		}
		else
		{
			$bot->say($cmd->channel,"Won't do!");
		}
	}
}

class Executor_RespondKick extends CommandExecutor
{
	
	function __construct()
	{
		parent::__construct(null,null);
		$this->irc_cmd = 'KICK';
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		if ( count($cmd->params) > 0 )
		{
			$who = $cmd->params[0];
			if ( $who != $bot->nick )
				$bot->say($cmd->channel, "We won't miss $who!" );
		}
	}
}


class Raw_Echo extends RawCommandExecutor
{
	public $phrase, $phrase_norm, $trim, $auth;
	
	function normalize($string)
	{
		return strtolower(trim(trim($string),$this->trim));
	}
	
	function __construct($phrase, $trim, $auth)
	{
		$this->phrase = $phrase;
		$this->trim = $trim;
		$this->auth = $auth;
		$this->phrase_norm = $this->normalize($phrase);
	}
	
	function check(MelanoBotCommand $cmd,MelanoBot $bot,BotData $driver)
	{
		return $cmd->cmd == null && $this->normalize($cmd->param_string(true)) == $this->phrase_norm && 
			$this->check_auth($cmd->from,$cmd->host,$bot,$driver);
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		$bot->say($cmd->channel,$this->phrase);
	}
}


class Executor_GreetingAllUsers extends CommandExecutor
{
	public $message;
	function __construct($message)
	{
		parent::__construct(null,null);
		$this->irc_cmd = 'JOIN';
		$this->message = $message;
	}
	
	function check(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		return $cmd->from != $bot->nick;
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		$message = str_replace('%',$cmd->from,$this->message);
		$bot->say($cmd->channel,$message);
	}
}

class Executor_GreetingUsers extends CommandExecutor
{
	public $messages;
	function __construct($messages)
	{
		parent::__construct(null,null);
		$this->irc_cmd = 'JOIN';
		$this->messages = $messages;
	}
	
	function check(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		return isset($this->messages[$cmd->from]);
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		$bot->say($cmd->channel,$this->messages[$cmd->from]);
	}
}

class Executor_GreetingSelf extends CommandExecutor
{
	public $message;
	function __construct($message)
	{
		parent::__construct(null,null);
		$this->irc_cmd = 'JOIN';
		$this->message = $message;
	}
	
	function check(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		return $cmd->from == $bot->nick;
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		$bot->say($cmd->channel,$this->message,1);
	}
}

class Executor_MiscListReadonly extends CommandExecutor
{
	public $list_name;
	public $list;
	
	function __construct($list_name, $auth=null,&$list_ref=null)
	{
		parent::__construct($list_name,$auth,"$list_name",
			"Show the values in the $list_name list");
		$this->list_name = $list_name;
		
		if ( isset($list_ref) )
			$this->list = &$list_ref;
	}
	
	function check(MelanoBotCommand $cmd,MelanoBot $bot,BotData $driver)
	{
		return count($cmd->params) == 0 && $this->check_auth($cmd->from,$cmd->host,$bot,$driver) ;
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		if ( !isset($this->list) )
			$this->list = &$driver->data[$this->list_name] ;
		
		if ( count($this->list) == 0 )
			$bot->say($cmd->channel,"(Empty list)");
		else
			$bot->say($cmd->channel,implode(" ",$this->list));
	}
}

class Executor_MiscListEdit extends CommandExecutor
{
	public $list_name;
	
	public $list;
	
	function __construct($list_name, $auth='admin',&$list_ref=null)
	{
		parent::__construct($list_name,$auth,"$list_name [+|add|-|rm value]|[clear]",
			"Add a value to the $list_name list");
			
		$this->list_name = $list_name;
		
		if ( isset($list_ref) )
			$this->list = &$list_ref;
	}
	
	function add_to_list($value)
	{
		$this->list []= $value;
		$this->list = array_unique($this->list);
	}
	
	function remove_from_list($value)
	{
		if (($key = array_search($value, $this->list)) !== false) 
		{
			array_splice($this->list,$key,1);
		}
	}
	
	function check(MelanoBotCommand $cmd,MelanoBot $bot,BotData $driver)
	{
		return count($cmd->params) > 0 && $this->check_auth($cmd->from,$cmd->host,$bot,$driver) ;
	}
	
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		if ( !isset($this->list) )
		{
			$driver->data[$this->list_name] = array();
			$this->list = &$driver->data[$this->list_name];
		}
		
		$remove = false;
		$nick_i = 1;
		if ( $cmd->params[0] == "add" || $cmd->params[0] == "+" )
			$remove = false;
		elseif ( $cmd->params[0] == "rm" || $cmd->params[0] == "-" ) 
			$remove = true;
		elseif ( $cmd->params[0] == "clear" )
		{
			$this->list = array();
			$bot->say($cmd->channel,"OK, {$this->list_name} cleared");
				
			return;
		}
		else
			$nick_i = 0;
			
		if ( !isset($cmd->params[$nick_i]) )
			$bot->say($cmd->channel,"What?");
		else
		{
			for ( $i = $nick_i; $i < count($cmd->params); $i++ )
			{
				$value = $cmd->params[$i];
				
				if ( $value != "" )
				{
					if ( $remove )
					{
						$this->remove_from_list($value);
						$bot->say($cmd->channel,"OK, $value is no longer in {$this->list_name}");
					}
					else
					{
						$this->add_to_list($value);
						$bot->say($cmd->channel,"OK, $value is in {$this->list_name}");
					}
				}
			}
		}
	}
	
}

class Executor_MiscList extends Executor_Multi
{
	function __construct($list_name, $auth_edit='admin',$auth_read=null,&$list_ref=null)
	{
		parent::__construct($list_name,array(
			new Executor_MiscListEdit($list_name,$auth_edit,$list_ref),
			new Executor_MiscListReadonly($list_name,$auth_read,$list_ref)
		));
	}
}


class Executor_Cointoss extends CommandExecutor
{
	public $default;
	function __construct($trigger="cointoss",$default=array("Heads","Tails"),$auth=null)
	{
		parent::__construct($trigger,$auth,"$trigger [values]","Get a random element out of the given values");
		$this->default = $default;
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		$values = $cmd->params;
		if ( count($values) < 1 )
			$values = $this->default;
		$bot->say($cmd->channel,$values[rand(0,count($values)-1)]);
	}
}


/**
 * \brief Geolocate (IPv4) up to city precision
 */
class Executor_GeoCity extends CommandExecutor
{
	public $geoip;
	function __construct($geoip, $trigger="geolocate",$auth="admin")
	{
		parent::__construct($trigger,$auth,"$trigger IPv4address|hostname",
			"Get geolocation info on the given address");
		$this->geoip = $geoip;
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		global $GEOIP_REGION_NAME;
		if ( count($cmd->params) == 0 )
		{
			$bot->say($cmd->channel,"No address...",16);
			return;
		}
		foreach ( $cmd->params as $param )
		{
			if ( preg_match("{([0-9]+(:?\.[0-9]+){3})(?::[0-9]+)?}",$param,$matches) )
				$ip = $matches[1];
			else
				$ip = gethostbyname($param);
			
			$record = geoip_record_by_addr($this->geoip, $ip);
			if ( $record )
				$bot->say($cmd->channel,"Address: $ip, ".
					"Country: {$record->country_name} ({$record->country_code}), ".
					"Region: {$record->region} (".$GEOIP_REGION_NAME[$record->country_code][$record->region]."), ".
					"City: {$record->city}"
					,16);
			else
				$bot->say($cmd->channel,"No info for address $param ($ip)",16);
		}
	}
}