<?php


class Raw_Question extends RawCommandExecutor
{
	function check(MelanoBotCommand $cmd, MelanoBot $bot, BotDriver $driver)
	{
		return $cmd != null && substr(trim($cmd->raw),-1) == '?' ;
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotDriver $driver)
	{
		if ( $cmd->cmd == 'where' )
		{
			$bot->say($cmd->channel, "Here: https://maps.google.com/?q=where+".urlencode($cmd->param_string()));
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
	
	function Executor_Morse($auth='admin')
	{
		parent::__construct('morse',$auth,"morse Text|.-.-.","Morse decode/encode");
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotDriver $driver)
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
	
	function Executor_ReverseText()
	{
		parent::__construct('reverse',null,"reverse Text...","Print text upside-down");
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotDriver $driver)
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
	function Executor_RenderPony($trigger,$ponypath)
	{
		parent::__construct($trigger,'owner',"$trigger [Pony name]","Draw a pretty pony");
		$this->ponypath = $ponypath;
	}
	
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotDriver $driver)
	{
		if ( isset($cmd->params[0]) )
		{
			$pony = strtolower($cmd->params[0]);
			if ( isset(self::$ponies[$pony]) )
			{
				$lines = file($this->ponypath."/".self::$ponies[$pony].".irc.txt", FILE_IGNORE_NEW_LINES);
				$i = 0;
				foreach ( $lines as $line )
				{
					$bot->say($cmd->channel,$line);
					$i++;
					usleep((1+$i/10)*1000000);
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

