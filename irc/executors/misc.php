<?php

require("misc/inflector.php");

class Executor_Echo extends CommandExecutor
{
	public $action;
	
	function Executor_Echo($trigger,$action=false,$auth=null)
	{
		$this->action = $action;
		parent::__construct($trigger,$auth,"$trigger Text...","Make the bot $trigger something");
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		$text = $cmd->param_string();
		if ( $this->action )
			$text = "{$this->action} $text";
		$bot->say($cmd->channel,$text,!!$this->action);
	}
}


class Executor_Action extends CommandExecutor
{
	
	function Executor_Action($trigger="please",$auth=null)
	{
		parent::__construct($trigger,$auth,"$trigger Action...",'Make the bot perform a chat action (Roleplaying)');
		$this->reports_error = true;
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		if ( $this->check_auth($cmd->from,$cmd->host,$driver) && count($cmd->params)>0 )
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
	
	function Executor_RespondKick()
	{
		parent::__construct(null,null);
		$this->irc_cmd = 'KICK';
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		$bot->say($cmd->channel, $cmd->from == $bot->nick ? "Why??" : "We won't miss {$cmd->from}!" );
	}
}

class Raw_What extends RawCommandExecutor
{
	function check(MelanoBotCommand $cmd,MelanoBot $bot,BotData $driver)
	{
		return $cmd->cmd != null;
	}
	
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		$bot->say($cmd->channel, "What?" );
	}
}


class Raw_Echo extends RawCommandExecutor
{
	public $phrase, $phrase_norm, $trim, $auth;
	
	function normalize($string)
	{
		return strtolower(trim(trim($string),$this->trim));
	}
	
	function Raw_Echo($phrase, $trim, $auth)
	{
		$this->phrase = $phrase;
		$this->trim = $trim;
		$this->auth = $auth;
		$this->phrase_norm = $this->normalize($phrase);
	}
	
	function check(MelanoBotCommand $cmd,MelanoBot $bot,BotData $driver)
	{
		return $cmd->cmd == null && $this->normalize($cmd->param_string(true)) == $this->phrase_norm && $this->check_auth($cmd->from,$cmd->host,$driver);
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		$bot->say($cmd->channel,$this->phrase);
	}
}


class Executor_GreetingAllUsers extends CommandExecutor
{
	public $message;
	function Executor_GreetingAllUsers($message)
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
	function Executor_GreetingUsers($messages)
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
	function Executor_GreetingSelf($message)
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
		$bot->say($cmd->channel,$this->message);
	}
}

class Executor_MiscListReadonly extends CommandExecutor
{
	public $list_name;
	public $list;
	
	function Executor_MiscListReadonly($list_name, $auth=null,&$list_ref=null)
	{
		parent::__construct($list_name,$auth,"$list_name",
			"Show the values in the $list_name list");
		$this->list_name = $list_name;
		
		if ( isset($list_ref) )
			$this->list = &$list_ref;
	}
	
	function check(MelanoBotCommand $cmd,MelanoBot $bot,BotData $driver)
	{
		return count($cmd->params) == 0 && $this->check_auth($cmd->from,$cmd->host,$driver) ;
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
	
	function Executor_MiscListEdit($list_name, $auth='admin',&$list_ref=null)
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
		return count($cmd->params) > 0 && $this->check_auth($cmd->from,$cmd->host,$driver) ;
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
	function Executor_MiscList($list_name, $auth_edit='admin',$auth_read=null,&$list_ref=null)
	{
		parent::__construct($list_name,array(
			new Executor_MiscListEdit($list_name,$auth_edit,$list_ref),
			new Executor_MiscListReadonly($list_name,$auth_read,$list_ref)
		));
	}
}