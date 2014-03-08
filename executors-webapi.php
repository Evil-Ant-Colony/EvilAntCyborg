<?php
require_once("bot-driver.php");

/*class Helper_JSON_API
{
	private $response_location = array();
	private $apiurl_pre;
	
	function Helper_JSON_API($response_location,$apiurl_pre,$apiurl_post="")
	{
		$this->apiurl_pre = $apiurl_pre;
		$this->apiurl_post = $apiurl_post;
		$this->response_location = $response_location;
	}
	
	private function get_recursive($structure,$i)
	{
		if ( $i > count($response_location) )
			return $structure;
		if ( !isset($structure[$response_location[$i]]) )
			return null;
		return $this->get($structure[$response_location[$i]],$i+1);
	}
	
	
	function get($search)
	{
		$url=$this->apiurl_pre.urlencode($search).$this->apiurl_post;
		$response = json_decode(file_get_contents($url),true);
		return $this->get_recursive($response,0);
	}
}*/

class Executor_GoogleImages extends CommandExecutor
{
	
	function Executor_GoogleImages()
	{
		parent::__construct("image",null,'image Search...',
		'Post an image matching the search term (using Google Images)');
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		$url="https://ajax.googleapis.com/ajax/services/search/images?v=1.0&rsz=1&q=".urlencode($cmd->param_string());
		$response = json_decode(file_get_contents($url),true);
		if ( isset($response["responseData"]["results"][0]["unescapedUrl"]) )
		{
			$bot->say($cmd->channel, $response["responseData"]["results"][0]["unescapedUrl"]);
		}
		else
		{
			//print_r($response);
			$bot->say($cmd->channel, "Didn't find any image of ".$cmd->param_string());
		}
	}
}

class Executor_Youtube extends CommandExecutor
{
	
	function Executor_Youtube()
	{
		parent::__construct("video",null,'video Search...',
		'Post a video matching the search term (using Youtube)');
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
        $url="https://gdata.youtube.com/feeds/api/videos?alt=json&max-results=1&q=".urlencode($cmd->param_string());
        $response = json_decode(file_get_contents($url),true);
        if ( isset($response["feed"]["entry"][0]["link"][0]["href"]) )
        {
            $bot->say($cmd->channel, $response["feed"]["entry"][0]["link"][0]["href"]);
            return true;
        }
        else
        {
            //print_r($response);
			$bot->say($cmd->channel, "http://www.youtube.com/watch?v=oHg5SJYRHA0");
		}
	}
}

class Raw_Youtube extends RawCommandExecutor
{
	
	function check(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		return strpos($cmd->raw,"www.youtube.com/watch?v=") !== false;
	}
	
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
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
			/*else
				print_r($response);*/
				
			$bot->say($cmd->channel,"Ha Ha! Nice vid {$cmd->from}! $title");
		}
	}
	
}


function elide_string($string,$length)
{
    $lines = explode("\n",wordwrap($string,$length));
    $text = $lines[0];
    if ( count($lines) > 1 )
        $text .= "...";
    return $text;
}


class Executor_Dictionary extends CommandExecutor
{
	
	function Executor_Dictionary($trigger="define")
	{
		parent::__construct($trigger,null,"$trigger Term...",
		'Find the definition of Term (using Urban Dictionary)');
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
        $url="http://api.urbandictionary.com/v0/define?term=".urlencode($cmd->param_string());
        $response = json_decode(file_get_contents($url),true);
        if ( isset($response["list"][0]["definition"]) )
        {
            $bot->say($cmd->channel, elide_string(str_replace(array("\n","\r")," ",$response["list"][0]["definition"]),400) );
        }
        else
        {
			$bot->say($cmd->channel, "I don't know what ".$cmd->param_string()." means");
            //print_r($response);
		}
	}
}
