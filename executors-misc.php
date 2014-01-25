<?php

require("inflector.php");

class Executor_Echo extends CommandExecutor
{
	public $action;
	
	function Executor_Echo($trigger,$action=false,$auth=null)
	{
		$this->action = $action;
		parent::__construct($trigger,$auth,"$trigger Text...","Make the bot $trigger something");
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotDriver $driver)
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
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotDriver $driver)
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
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotDriver $driver)
	{
		$bot->say($cmd->channel, $cmd->from == $bot->nick ? "Why??" : "We won't miss {$cmd->from}!" );
	}
}

class Raw_What extends RawCommandExecutor
{
	function check(MelanoBotCommand $cmd,MelanoBot $bot,BotDriver $driver)
	{
		return $cmd->cmd != null;
	}
	
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotDriver $driver)
	{
		$bot->say($cmd->channel, "What?" );
	}
}


class Raw_Youtube extends RawCommandExecutor
{
	
	function check(MelanoBotCommand $cmd, MelanoBot $bot, BotDriver $driver)
	{
		return strpos($cmd->raw,"www.youtube.com/watch?v=") !== false;
	}
	
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotDriver $driver)
	{
		$match = array();
		if ( preg_match("{www\.youtube\.com/watch\?v=([-_0-9a-zA-Z]+)}",$cmd->raw,$match) )
		{
			$url="http://gdata.youtube.com/feeds/api/videos/{$match[1]}?alt=json";
			$response = json_decode(file_get_contents($url),true);
			$title = "";
			if ( isset($response["entry"]["title"]['$t']) )
			{
				$title = $response["entry"]["title"]['$t'];
			}
			else
				print_r($response);
				
			$bot->say($cmd->channel,"Ha Ha! Nice vid {$cmd->from}! $title");
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
	
	function Raw_Echo($phrase, $trim, $auth)
	{
		$this->phrase = $phrase;
		$this->trim = $trim;
		$this->auth = $auth;
		$this->phrase_norm = $this->normalize($phrase);
	}
	
	function check(MelanoBotCommand $cmd,MelanoBot $bot,BotDriver $driver)
	{
		return $cmd->cmd == null && $this->normalize($cmd->param_string(true)) == $this->phrase_norm && $this->check_auth($cmd->from,$cmd->host,$driver);
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotDriver $driver)
	{
		$bot->say($cmd->channel,$this->phrase);
	}
}

class Executor_GoogleImages extends CommandExecutor
{
	
	function Executor_GoogleImages()
	{
		parent::__construct("image",null,'image Search...',
		'Post an image matching the search term (using Google Images)');
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotDriver $driver)
	{
		$url="https://ajax.googleapis.com/ajax/services/search/images?v=1.0&rsz=1&q=".urlencode($cmd->param_string());
		$response = json_decode(file_get_contents($url),true);
		if ( isset($response["responseData"]["results"][0]["unescapedUrl"]) )
		{
			$bot->say($cmd->channel, $response["responseData"]["results"][0]["unescapedUrl"]);
		}
		else
			print_r($response);
	}
}

class Executor_Youtube extends CommandExecutor
{
	
	function Executor_Youtube()
	{
		parent::__construct("video",null,'video Search...',
		'Post a video matching the search term (using Youtube)');
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotDriver $driver)
	{
        $url="https://gdata.youtube.com/feeds/api/videos?alt=json&max-results=1&q=".urlencode($cmd->param_string());
        $response = json_decode(file_get_contents($url),true);
        if ( isset($response["feed"]["entry"][0]["link"][0]["href"]) )
        {
            $bot->say($cmd->channel, $response["feed"]["entry"][0]["link"][0]["href"]);
            return true;
        }
        else
            print_r($response);
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
	
	function check(MelanoBotCommand $cmd, MelanoBot $bot, BotDriver $driver)
	{
		return isset($this->messages[$cmd->from]);
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotDriver $driver)
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
	
	function check(MelanoBotCommand $cmd, MelanoBot $bot, BotDriver $driver)
	{
		return $cmd->from == $bot->nick;
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotDriver $driver)
	{
		$bot->say($cmd->channel,$this->message);
	}
}