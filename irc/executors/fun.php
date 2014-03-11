<?php


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
					$bot->say($cmd->channel,$line);
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
		'roseluck' => 'roseluck',
		'trixie' => 'trixie-hat',
		'twilight' => 'twilight-alicorn',
		'unicorn' => 'twilight-unicorn',
		'alicorn' => 'twilight-alicorn',
		'princess' => 'twilight-alicorn',
		'vinyl' => 'vinyl-scratch-noglasses',
		'dj' => 'vinyl-scratch-glasses',
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
				$this->check_auth($cmd->from,$cmd->host,$driver) );
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