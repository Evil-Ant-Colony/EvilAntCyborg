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

require_once("irc/bot-driver.php");

/*class Helper_JSON_API
{
	private $response_location = array();
	private $apiurl_pre;
	
	function __construct($response_location,$apiurl_pre,$apiurl_post="")
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
	
	function __construct()
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
	
	function __construct()
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
	
	function __construct($trigger="define")
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



class Executor_GoogleTranslate  extends CommandExecutor
{
	
	static $language_codes = array(
		"Afrikaans" => "af",
		"Albanian" => "sq",
		"Arabic" => "ar",
		"Azerbaijani" => "az",
		"Basque" => "eu",
		"Bengali" => "bn",
		"Belarusian" => "be",
		"Bulgarian" => "bg",
		"Catalan" => "ca",
		"Chinese" => "zh-CN",
		//"Chinese Simplified" => "zh-CN",
		//"Chinese Traditional" => "zh-TW",
		"Croatian" => "hr",
		"Czech" => "cs",
		"Danish" => "da",
		"Dutch" => "nl",
		"English" => "en",
		"Esperanto" => "eo",
		"Estonian" => "et",
		"Filipino" => "tl",
		"Finnish" => "fi",
		"French" => "fr",
		"Galician" => "gl",
		"Georgian" => "ka",
		"German" => "de",
		"Greek" => "el",
		"Gujarati" => "gu",
		"Haitian" => "ht",
		//"Haitian Creole" => "ht",
		"Hebrew" => "iw",
		"Hindi" => "hi",
		"Hungarian" => "hu",
		"Icelandic" => "is",
		"Indonesian" => "id",
		"Irish" => "ga",
		"Italian" => "it",
		"Japanese" => "ja",
		"Kannada" => "kn",
		"Korean" => "ko",
		"Latin" => "la",
		"Latvian" => "lv",
		"Lithuanian" => "lt",
		"Macedonian" => "mk",
		"Malay" => "ms",
		"Maltese" => "mt",
		"Norwegian" => "no",
		"Persian" => "fa",
		"Polish" => "pl",
		"Portuguese" => "pt",
		"Romanian" => "ro",
		"Russian" => "ru",
		"Serbian" => "sr",
		"Slovak" => "sk",
		"Slovenian" => "sl",
		"Spanish" => "es",
		"Swahili" => "sw",
		"Swedish" => "sv",
		"Tamil" => "ta",
		"Telugu" => "te",
		"Thai" => "th",
		"Turkish" => "tr",
		"Ukrainian" => "uk",
		"Urdu" => "ur",
		"Vietnamese" => "vi",
		"Welsh" => "cy",
		"Yiddish" => "yi",
	);
	function __construct($trigger="translate")
	{
		parent::__construct($trigger,null,"$trigger [from Language] [into Language] Phrase...",
			'Make the bot translate the given Phrase (using Google)');
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		$sl="";
		$tl="en";
		
		if ( count($cmd->params) > 2 )
		{
		
			$direction = $cmd->params[0];
			if ( $direction == 'from' )
			{
				array_shift($cmd->params);
				$lang_from = ucfirst(array_shift($cmd->params));
				if ( isset(self::$language_codes[$lang_from]) )
					$sl = self::$language_codes[$lang_from];
				else
				{
					$bot->say($cmd->channel,"I'm sorry but I don't speak $lang_from");
					return;
				}
				$direction = $cmd->params[0];
			}
			
			if ( $direction == 'into' )
			{
				array_shift($cmd->params);
				$lang_to = ucfirst(array_shift($cmd->params));
				if ( isset(self::$language_codes[$lang_to]) )
					$tl = self::$language_codes[$lang_to];
				else
				{
					$bot->say($cmd->channel,"I'm sorry but I don't speak $lang_to");
					return;
				}
			}
			
		}
			
		
		$url="http://translate.google.com/translate_a/t?client=t&sl=$sl&tl=$tl&ie=UTF-8&oe=UTF-8&text=".urlencode($cmd->param_string());
		$response = file_get_contents($url);
		if ( preg_match('{^\[\[\["(([^\\\\"]|(\\\\.))+)}',$response,$matches) )
		{
			$bot->say($cmd->channel, stripslashes($matches[1]) );
		}
		else
		{
			echo "$url\n";
			print_r($response);
			$bot->say($cmd->channel,"I'm sorry but I can't translate that...");
		}
	}
}

