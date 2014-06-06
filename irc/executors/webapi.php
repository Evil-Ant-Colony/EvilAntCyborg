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
		return strpos($cmd->raw,"youtu.be/") !== false || strpos($cmd->raw,"www.youtube.com/watch?v=") !== false;
	}
	
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		$match = array();
		if ( preg_match("{(?:www\.youtube\.com/watch\?v=|youtu\.be/)([-_0-9a-zA-Z]+)}",$cmd->param_string(),$match) )
		{
			$url="http://gdata.youtube.com/feeds/api/videos/{$match[1]}?alt=json";
			$response = json_decode(file_get_contents($url),true);
			$title = "";
			$duration = "";
			if ( isset($response["entry"]["title"]['$t']) )
			{
				$title = $response["entry"]["title"]['$t'];
			}
			if ( isset($response["entry"]['media$group']['yt$duration']['seconds']) )
			{
				$seconds = (int) $response["entry"]['media$group']['yt$duration']['seconds'];
				$minutes = floor($seconds / 60);
				$seconds %= 60;
				$duration = "$minutes:$seconds";
				if ( $minutes >= 60 )
				{
					$hours = floor($minutes / 60);
					$minutes %= 60;
					$duration = "$hours:$minutes:$seconds";
				}
				$duration = " (\002$duration\xf)";
			}
				
			$bot->say($cmd->channel,"Ha Ha! Nice vid {$cmd->from}! $title$duration");
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

class GoogleTranslator
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
	
	
	static function language_code($language)
	{
		$language = ucfirst($language);
		if ( isset(self::$language_codes[$language]) )
			return self::$language_codes[$language];
		$language = strtolower($language);
		if ( in_array($language,self::$language_codes) )
			return $language;
		return null;
	}
	
	static function translate($sl,$tl,$text)
	{
		$url="http://translate.google.com/translate_a/t?client=t&sl=$sl&tl=$tl&ie=UTF-8&oe=UTF-8&text=".
			urlencode($text);
		
		$response = file_get_contents($url);
		if ( preg_match('{^\[\[\["(([^\\\\"]|(\\\\.))+)}',$response,$matches) )
			return stripslashes($matches[1]);
			
		return null;
	}
}

class Executor_GoogleTranslate  extends CommandExecutor
{
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
				if ( isset(GoogleTranslator::$language_codes[$lang_from]) )
					$sl = GoogleTranslator::$language_codes[$lang_from];
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
				if ( isset(GoogleTranslator::$language_codes[$lang_to]) )
					$tl = GoogleTranslator::$language_codes[$lang_to];
				else
				{
					$bot->say($cmd->channel,"I'm sorry but I don't speak $lang_to");
					return;
				}
			}
			
		}
			
		$translated = GoogleTranslator::translate($sl,$tl,$cmd->param_string());
		if ( $translated )
		{
			$bot->say($cmd->channel, $translated );
		}
		else
		{
			$bot->say($cmd->channel,"I'm sorry but I can't translate that...");
		}
	}
}

class Executor_Autotranslate  extends RawCommandExecutor
{
	public $chan_nicks = array();
	public $target_language='en';
	public $trigger;
	public $enabled;
	public $auth_enable;
	
	function __construct($trigger = 'autotranslate', $auth = 'admin', $auth_enable = 'owner', $start_enabled = true)
	{
		$this->trigger = $trigger;
		$this->auth = $auth;
		$this->enabled = $start_enabled;
		$this->auth_enable = $auth_enable;
	}
	
	function check(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		return ( $cmd->cmd == $this->trigger && $this->check_auth($cmd->from,$cmd->host,$bot,$data) ) ||  
			( $cmd->cmd == null && $this->enabled &&
			!empty($this->chan_nicks[$cmd->channel]) && 
			in_array($cmd->from,$this->chan_nicks[$cmd->channel]) );
	}
	
	
	/// Check that the user can run the executor
	function check_auth_enable($nick,$host,MelanoBot $bot, BotData $data)
	{
		return !$this->auth_enable || $data->user_in_list($this->auth_enable,$bot->get_user($nick,$host));
	}
	
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		if ( $cmd->cmd == $this->trigger )
		{
			switch ( $cmd->params[0] )
			{
				case 'clear':
					$this->chan_nicks = array();
					$bot->say($cmd->channel, "Removed all automatic translations");
					break;
				case '+':
				case 'add':
					$users = isset($this->chan_nicks[$cmd->channel]) ? $this->chan_nicks[$cmd->channel] : array();
					for ( $i = 1; $i < count($cmd->params); $i++ )
						if ( strlen($cmd->params[$i]) > 0 )
							$users[]= $cmd->params[$i];
					$users = array_unique($users);
					$this->chan_nicks[$cmd->channel] = $users;
					$bot->say($cmd->channel, "Autotranslation for $cmd->channel: ".implode(", ",$users));
					break;
				case '-':
				case 'rm':
					for ( $i = 1; $i < count($cmd->params); $i++ )
					{
						if ( strlen($cmd->params[$i]) > 0 )
						{
							if ( $cmd->params[$i][0] == '#' )
							{
								if ( isset($this->chan_nicks[$cmd->params[$i]]) ) 
									unset($this->chan_nicks[$cmd->params[$i]]);
							}
							else if ( !empty($this->chan_nicks[$cmd->channel]) )
							{
								if ( ($key = array_search($cmd->params[$i], $this->chan_nicks[$cmd->channel])) !== false) 
									array_splice($this->chan_nicks[$cmd->channel],$key,1);
							}
						}
					}
					$bot->say($cmd->channel, "Removed given automatic translations");
					break;
				case 'view':
					if ( count($cmd->params) == 1 )
					{
						$chans = array();
						foreach($this->chan_nicks as $chan => $nicks)
						{
							if ( count($nicks) > 0 )
								$chans []="$chan (".count($nicks).")";
						}
						if ( count($chans) == 0 )
							$bot->say($cmd->channel,"No active automatic translations ");
						else
							$bot->say($cmd->channel,implode(", ",$chans));
					}
					else
					{
						if ( empty($this->chan_nicks[$cmd->params[1]]) )
							$bot->say($cmd->channel,"No automatic translations on {$cmd->params[1]}");
						else
							$bot->say($cmd->channel,"{$cmd->params[1]}: ".implode(", ",$this->chan_nicks[$cmd->params[1]]));
					}
					break;
				case 'enable':
					if ( $this->check_auth_enable($cmd->from,$cmd->host,$bot,$data) )
					{
						$this->enabled = true;
						$bot->say($cmd->channel, "Autotranslation \00309enabled\xf");
					}
					break;
				case 'disable':
					if ( $this->check_auth_enable($cmd->from,$cmd->host,$bot,$data) )
					{
						$this->enabled = false;
						$bot->say($cmd->channel, "Autotranslation \00304disabled\xf");
					}
					break;
				default:
					$this->help($cmd, $bot, $data);
			}
				
		}
		else
		{
			$translated = GoogleTranslator::translate("",$this->target_language,$cmd->param_string());
			if ( $translated )
				$bot->say($cmd->channel, "<$cmd->from> $translated", -1 );
		}
	}
	
	function name()
	{
		return $this->trigger;
	}
	
	function help(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		$en_dis="";
		if ( $this->check_auth_enable($cmd->from,$cmd->host,$bot,$data) )
			$en_dis ="|enable|disable";
		$bot->say($cmd->channel,"\x0304".$this->name()."\x03: \x0314{$this->trigger} clear|+ user...|- (user|#channel)...|view [#channel]$en_dis\x03");
		$bot->say($cmd->channel,"\x0302Manage automatic translations\x03");
	}
	
}
