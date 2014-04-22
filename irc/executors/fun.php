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


class Raw_Question extends RawCommandExecutor
{
	function check(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		return $cmd->cmd != null && substr(trim($cmd->raw),-1) == '?' ;
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		if ( $cmd->cmd == 'where' )
		{
			$param_string = urlencode("where ".$cmd->param_string());
			$ll = "";
			$url="http://maps.googleapis.com/maps/api/geocode/json?sensor=false&address=$param_string";
			$response = json_decode(file_get_contents($url),true);
			$name = "I don't know";
			if ( isset($response["results"][0]["formatted_address"]) )
				$name = $response["results"][0]["formatted_address"];
			if ( isset($response["results"][0]["geometry"]["location"]) )
				$ll = "ll=".$response["results"][0]["geometry"]["location"]["lat"].",".
					  $response["results"][0]["geometry"]["location"]["lng"];
			$bot->say($cmd->channel, "$name: https://maps.google.com/?$ll&q=$param_string");
		}
		else
			$bot->say($cmd->channel, self::$fake_answers[rand(0,count(self::$fake_answers)-1)]);
	}
	
	
	static public $fake_answers = array(
		'Signs point to yes',
		'Yes',
		'Without a doubt',
		'As I see it, yes',
		'It is decidedly so',
		'Of course',
		'Most likely',
		'Sure!',

		'Maybe',

		'Concentrate and ask again',
		'Better not tell you now',
		'Cannot predict now',
		'Ask again later',
		"I don't know",

		'Maybe not',

		'My reply is no',
		'My sources say no',
		'Very doubtful',
		"Don't count on it",
		"I don't think so",
		"Nope",
		"No way!",
		"No",

	);
}

class Executor_Morse extends CommandExecutor
{
	
	function __construct($auth='admin')
	{
		parent::__construct('morse',$auth,"morse Text|.-.-.","Morse decode/encode");
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		if ( preg_match("/^[-. ]+$/",$cmd->param_string()) )
		{
			$result_string = "";
			$morse_array = explode(" ",$cmd->param_string());
			foreach ( $morse_array as $mc )
			{
				if ( strlen($mc) == 0 )
					$result_string .= " ";
				else
					foreach(self::$morse as $c => $m)
						if ( $m == $mc )
						{
							$result_string .= $c;
							break;
						}
			}
			$bot->say($cmd->channel,$result_string);
		}
		else
		{
			$morse_string = array();
			$param_string = strtolower($cmd->param_string());
			for ( $i = 0; $i < strlen($param_string); $i++ )
				if ( isset(self::$morse[$param_string[$i]]) )
					$morse_string []= self::$morse[$param_string[$i]];
			$bot->say($cmd->channel, implode(" ",$morse_string));
		}
	}
	
	static public $morse = array(
		'a' => ".-",
		'b' => "-...",
		'c' => "-.-.",
		'd' => "-..",
		'e' => ".",
		'f' => "..-.",
		'g' => "--.",
		'h' => "....",
		'i' => "..",
		'j' => ".---",
		'k' => "-.-",
		'l' => ".-..",
		'm' => "--",
		'n' => "-.",
		'o' => "---",
		'p' => ".--.",
		'q' => "--.-",
		'r' => ".-.",
		's' => "...",
		't' => "-",
		'u' => "..-",
		'v' => "...-",
		'w' => ".---",
		'x' => "-..-",
		'y' => "-.--",
		'z' => "--..",
		'0' => "-----",
		'1' => ".----",
		'2' => "..---",
		'3' => "...--",
		'4' => "....-",
		'5' => ".....",
		'6' => "-....",
		'7' => "--...",
		'8' => "---..",
		'9' => "----.",
		'.' => ".-.-.-",
		',' => "--..--",
		'?' => "..--..",
		'\'' => ".----.",
		'!' => "-.-.--",
		'/' => "-..-.",
		'(' => "-.--.",
		')' => "-.--.-",
		'&' => ".-...",
		':' => "---...",
		';' => "-.-.-.",
		'=' => "-...-",
		'+' => ".-.-.",
		'-' => "-....-",
		'_' => "..--.-",
		'"' => ".-..-.",
		'$' => "...-..-",
		'@' => ".--.-.",
		' ' => "",
		'<' => ".-.........",
		'>' => "--..-...--..-.",
	);
}


class Executor_ReverseText extends CommandExecutor
{
	
	function __construct()
	{
		parent::__construct('reverse',null,"reverse Text...","Print text upside-down");
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		$rev = "";
		$param_string = $cmd->param_string();
		for ( $i = 0; $i < strlen($param_string); $i++ )
		{
			$c = $param_string[$i];
			if ( isset(self::$reverse_ASCII[$c]) )
				$rev = self::$reverse_ASCII[$c].$rev;
			else
				$rev .= "$c$rev";
		}
		$bot->say($cmd->channel, $rev);
	}
	
	public static $reverse_ASCII = array(
		' ' => ' ',
		'!' => '¡',
		'"' => '„',
		'#' => '#',
		'$' => '$',
		'%' => '%', // :-(
		'&' => '⅋',
		'\'' => 'ˌ',
		'(' => ')',
		')' => '(',
		'*' => '*',
		'+' => '+',
		',' => 'ʻ',
		'-' => '-',
		'.' => '˙',
		'/' => '\\',
		'0' => '0',
		'1' => '⇂', // Ɩ
		'2' => 'ح',//ᄅ
		'3' => 'Ꜫ',
		'4' => 'ᔭ',
		'5' => '2', // meh
		'6' => '9',
		'7' => 'ㄥ',
		'8' => '8',
		'9' => '6',
		':' => ':',
		';' => '؛',
		'<' => '>',
		'=' => '=',
		'>' => '<',
		'?' => '¿',
		'@' => '@', // :-(
		'A' => 'Ɐ',
		'B' => 'ᗺ',
		'C' => 'Ɔ',
		'D' => 'ᗡ',
		'E' => 'Ǝ',
		'F' => 'Ⅎ',
		'G' => '⅁',
		'H' => 'H',
		'I' => 'I',
		'J' => 'ſ',
		'K' => 'ʞ', // :-/
		'L' => 'Ꞁ',
		'M' => 'ꟽ',
		'N' => 'N',
		'O' => 'O',
		'P' => 'd',// meh
		'Q' => 'Ò',
		'R' => 'ᴚ',
		'S' => 'S',
		'T' => '⊥',
		'U' => '⋂',
		'V' => 'Λ',
		'W' => 'M', // meh
		'X' => 'X',
		'Y' => '⅄',
		'Z' => 'Z',
		'[' => ']',
		'\\' => '/',
		']' => '[',
		'^' => '˯',
		'_' => '¯',
		'`' => 'ˎ',
		'a' => 'ɐ',
		'b' => 'q',
		'c' => 'ɔ',
		'd' => 'p',
		'e' => 'ə',
		'f' => 'ɟ',
		'g' => 'δ',
		'h' => 'ɥ',
		'i' => 'ᴉ',
		'j' => 'ɾ',
		'k' => 'ʞ',
		'l' => 'ꞁ',
		'm' => 'ɯ',
		'n' => 'u',
		'o' => 'o',
		'p' => 'd',
		'q' => 'b',
		'r' => 'ɹ',
		's' => 's',
		't' => 'ʇ',
		'u' => 'n',
		'v' => 'ʌ',
		'w' => 'ʍ',
		'x' => 'x',
		'y' => 'ʎ',
		'z' => 'z',
		'{' => '}',
		'|' => '|',
		'}' => '{',
		'~' => '∽',
	);
}


class Executor_RenderPony extends CommandExecutor
{
	public $ponypath;
	function __construct($trigger,$ponypath)
	{
		parent::__construct($trigger,'owner',"$trigger [Pony name]","Draw a pretty pony");
		$this->ponypath = $ponypath;
	}
	
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		if ( isset($cmd->params[0]) )
		{
			$pony = strtolower($cmd->params[0]);
			if ( isset(self::$ponies[$pony]) )
			{
				$lines = file($this->ponypath."/".self::$ponies[$pony].".irc.txt", FILE_IGNORE_NEW_LINES);
				foreach ( $lines as $line )
				{
					$bot->say($cmd->channel,$line,-512);
				}
			}
			else
			{
				$bot->say($cmd->channel,"Sorry {$cmd->from} but I don't know ".
					ucwords($cmd->param_string())
				);
			}
		}
		else
			$bot->say($cmd->channel,"Pony!!!");
	}
	
	static public $ponies = array(
		'applejack' => 'applejack-nohat',
		'derpy' => 'derpy',
		'fluttershy' => 'fluttershy',
		'lyra' => 'lyra',
		'pinkie' => 'pinkie-pie',
		'pinkamena' => 'pinkie-pie',
		'rainbow' => 'rainbow-dash',
		'rarity' => 'rarity',
		'rose' => 'rose',
		'roseluck' => 'rose',
		'trixie' => 'trixie-hat',
		'twilight' => 'twilight-alicorn',
		'unicorn' => 'twilight-unicorn',
		'alicorn' => 'twilight-alicorn',
		'vinyl' => 'vinyl-scratch-noglasses',
		'dj' => 'vinyl-scratch-glasses',
		'princess' => 'celestia',
		'celestia' => 'celestia',
	);

}




class Raw_Annoy extends RawCommandExecutor
{
	public $terms;
	public $toggler;
	public $enabled;
	
	function __construct($toggler,$auth='admin')
	{
		$this->auth = $auth;
		$this->terms=array('LOL','ROFL','XD','><',':D','OMG','WTF');
		$this->toggler = strtoupper($toggler);
		$this->enabled = true;
	}
	
	function check(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		return ( $cmd->cmd == null && $this->enabled && count($cmd->params) == 1 
				&& in_array(strtoupper($cmd->params[0]),$this->terms) )
				|| ( strtoupper($cmd->cmd) == $this->toggler && 
				$this->check_auth($cmd->from,$cmd->host,$bot,$driver) );
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		if ( strtoupper($cmd->cmd) == $this->toggler )
			$this->enabled = !$this->enabled;
		else
		{
			$bot->say($cmd->channel,$this->terms[rand()%count($this->terms)]." he said ".$cmd->params[0] );
		}
	}
	
}


class Executor_Discord extends CommandExecutor
{
	static $day = array("Sweetmorn", "Boomtime", "Pungenday", "Prickle-Prickle", "Setting Orange");
	static $season = array ("Chaos", "Discord", "Confusion", "Bureaucracy", "The Aftermath");
	static $song = array(
		"I'm not a fan of puppeteers but I've a nagging fear someone else is pulling at the strings",
		"Something terrible is going down through the entire town wreaking anarchy and all it brings",
		"I can't sit idly, no, I can't move at all. I curse the name, the one behind it all...",
		"Discord, I'm howlin' at the moon and sleepin' in the middle of a summer afternoon",
		"Discord, whatever did we do to make you take our world away?",
		"Discord, are we your prey alone, or are we just a stepping stone for taking back the throne?",
		"Discord, we won't take it anymore so take your tyranny away!",
		"I'm fine with changing status quo, but not in letting go now the world is being torn apart",
		"A terrible catastrophe played by your symphony, what a terrifying work of art!"
	);
	static function get_date($time=null)
	{
		if ( $time == null )
			$time = time();
		$time = localtime($time,true);
		$day = $time["tm_yday"];
		$year = $time["tm_year"] + 3066;
		$season = 0;
		if ( $year % 4 == 2 )
		{
			if ( $day == 59 )
				$day = -1;
			else if ( $day > 59 )
				$day -= 1;
		}
		$yday = $day;
		while ( $day >= 73 )
		{
			$season++;
			$day -= 73;
		}
		
		global $english_ordinal;
		return "Today is ".self::$day[$yday%5].", the ".$english_ordinal->inflect($day+1).
			" day of ".self::$season[$season]." in the YOLD $year";
	}
	
	function __construct($trigger='discord')
	{
		parent::__construct($trigger,null,"$trigger [time]","Show the Discordian date");
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		if ( count($cmd->params) > 0 )
		{
			if ( $cmd->params[0] == 'sing' )
				$string = self::$song[rand(0,count(self::$song)-1)];
			else
				$string = self::get_date(strtotime($cmd->param_string()));
		}
		else
			$string = self::get_date();
		$bot->say($cmd->channel,$string);
	}
};