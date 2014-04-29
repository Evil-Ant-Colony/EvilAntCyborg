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

/**
 * \brief Store a color code and convert colored strings
 */
class Color
{
	const NOCOLOR= null;
	
	const BLACK  = 0;
	const RED    = 1;
	const GREEN  = 2;
	const YELLOW = 3;
	const BLUE   = 4;
	const MAGENTA= 5;
	const CYAN   = 6;
	const WHITE  = 7;
	
	const BRIGHT = true;
	const DARK   = false;

	public $code; ///< 7 bit rgb, null means no color
	public $bright; ///< if true, brighter color
	
	private static $irc_regex = "{(\3([0-9][0-9]?)?(,[0-9][0-9]?)?)|\xf|\1|\2|\x16|\x1f}";
	private static $dp_regex = "/(\^\^)|(\^[0-9])|(\^x[0-9a-fA-F]{3})/";
	
	function __construct($code, $bright=false)
	{
		$this->code = (int)$code;
		$this->bright = $bright;
	}
	
// string conversion
	/**
	 * \brief Strip colors and special characters from an IRC colored string
	 */
	static function irc2none($string)
	{
		return preg_replace(self::$irc_regex,"",$string);
	}
	
	/**
	 * \brief Convert from an IRC-encoded string into an ANSI encoded one
	 */
	static function irc2ansi($string)
	{
		
		return preg_replace_callback(self::$irc_regex,
			function ($matches)
			{
				if ( count($matches) > 2 )
					return Color::from_irc($matches[2])->ansi();
				return "\x1b[0m";
			},$string);
	}
	
	/**
	 * \brief Convert from an IRC-encoded string into a Darkplaces colored string
	 */
	static function irc2dp($string)
	{
		
		return preg_replace_callback(self::$irc_regex,
			function ($matches)
			{
				if ( count($matches) > 2 )
					return Color::from_irc($matches[2])->dp();
				return "^7";
			},$string);
	}
	
	/**
	 * \brief Convert from a Darkplaces string into a string with IRC color codes
	 */
	static function dp2irc($string)
	{
		return preg_replace_callback(self::$dp_regex,
			function ($matches)
			{
				if ( $matches[0] == "^^" )
					return "^";
				return Color::from_dp($matches[0])->irc();
			}
			,self::dp_char_convert($string))."\xf";
	}
	
	/**
	 * \brief Strip colors from a DP colored string
	 */
	static function dp2none($string)
	{
		return preg_replace_callback(self::$dp_regex,
			function ($matches)
			{
				if ( $matches[0] == "^^" )
					return "^";
				return "";
			}
			,self::dp_char_convert($string));
	}
	
	/**
	 * \brief Convert a colored DP string to a colored ANSI string
	 */
	static function dp2ansi($string)
	{
		return preg_replace_callback(self::$dp_regex,
			function ($matches)
			{
				if ( $matches[0] == "^^" )
					return "^";
				return Color::from_dp($matches[0])->ansi();
			}
			,self::dp_char_convert($string))."\x1b[0m";
	}
	
// static constructors
	/**
	 * \brief Create a color from a 3 digit hex string
	 */
	static function from_12hex($color)
	{
		$color = "$color";
		if ( strlen($color) < 3 )
			return new Color(self::NOCOLOR);
		$r = hexdec($color[0]); $g = hexdec($color[1]); $b = hexdec($color[2]);
		
		$v = max($r,$g,$b);
		$cmin = min($r,$g,$b);
		$D = $v - $cmin;
		
		if ( $D == 0 )
		{
			$c = 0;
		}
		else 
		{
			if ( $r == $v )
				$h = ($g-$b)/$D;
			else if ( $g == $v )
				$h = ($b-$r)/$D + 2;
			else if ( $b == $v )
				$h = ($r-$g)/$D + 4;
				
			$s = $D / $v;
		
			if ( $s >= 0.3 )
			{
				if ( $h < 0 ) $h += 6;
				if ( $h < 0.5 )      $c = self::RED;
				else if ( $h < 1.5 ) $c = self::YELLOW;
				else if ( $h < 2.5 ) $c = self::GREEN;
				else if ( $h < 3.5 ) $c = self::CYAN;
				else if ( $h < 4.5 ) $c = self::BLUE;
				else if ( $h < 5.5 ) $c = self::MAGENTA;
				else $c = self::RED;
			}
			elseif ( $v > 7 )
				$c = 7;
			else
				$c = 0;
		}
		return new Color($c,$v>9);
		/*$rt = $r > 3; $gt = $g > 3; $bt = $b > 3;
		return new Color($rt|($gt<<1)|($bt<<2), $r > 9 || $b > 9 || $g > 9);*/
	}
	
	/**
	 * \brief Create a color from a DP ^string
	 */
	static function from_dp($color)
	{
		if ( strlen($color) == 2 && ($code = (int)$color[1]) < 8 ) // ^N
		{
			switch ( $code )
			{
				case 5: return new Color(6,self::BRIGHT);
				case 6: return new Color(5,self::BRIGHT);
				default: return new Color((int)$color[1],self::BRIGHT);
			}
		}
		else if ( strlen($color) == 5 ) // ^xNNN
			return self::from_12hex(substr($color,2));
		return new Color(self::NOCOLOR);
	}
	
	/**
	 * \brief Create a color from an IRC color number
	 */
	static function from_irc($color)
	{
		switch((int)$color)
		{
			case 14:
				return new Color(self::BLACK,self::BRIGHT);
			case 1: 
				return new Color(self::BLACK,self::DARK);
			case 4:
				return new Color(self::RED,self::BRIGHT);
			case 5:
				return new Color(self::RED,self::DARK);
			case 9: 
				return new Color(self::GREEN,self::BRIGHT);
			case 3:
				return new Color(self::GREEN,self::DARK);
			case 8: 
				return new Color(self::YELLOW,self::BRIGHT);
			case 7:
				return new Color(self::YELLOW,self::DARK);
			case 12:
				return new Color(self::BLUE,self::BRIGHT);
			case 2:
				return new Color(self::BLUE,self::DARK);
			case 13:
				return new Color(self::MAGENTA,self::BRIGHT);
			case 6:
				return new Color(self::MAGENTA,self::DARK);
			case 11:
				return new Color(self::CYAN,self::BRIGHT);
			case 10:
				return new Color(self::CYAN,self::DARK);
			case 0:
				return new Color(self::WHITE,self::BRIGHT);
			case 15:
				return new Color(self::WHITE,self::DARK);
			default: 
				return new Color(self::NOCOLOR);
		}
	}
	
// color to string conversions
	/**
	 * \brief Get the ANSI escape sequence representing this color
	 * \note Converts black to white to display nicely on terminals with a black background
	 */
	function ansi()
	{
		if ( $this->code == null )
			return "\x1b[0m";
		$c = $this->bright ? 9 : 3;
		if ( $this->code < 1 || $this->code > 7 )
			$this->code = 7;
		return "\x1b[$c{$this->code}m";
	}

	/**
	 * \brief Get the IRC color sequence representing this color
	 * \note Displays bright yellow as dark yellow and white as defult color to look nicely on a bright background
	 */
	function irc()
	{
		$out = "";
		if ( $this->bright )
			switch($this->code)
			{
				case 0: $out = "14"; break;
				case 1: $out = "04"; break;
				case 2: $out = "09"; break;
				case 3: $out = "07"; break; // no bright yellow
				case 4: $out = "12"; break;
				case 5: $out = "13"; break;
				case 6: $out = "11"; break;
				// no white
			}
		else
			switch($this->code)
			{
				case 0: $out = "01"; break;
				case 1: $out = "05"; break;
				case 2: $out = "03"; break;
				case 3: $out = "07"; break;
				case 4: $out = "02"; break;
				case 5: $out = "06"; break;
				case 6: $out = "10"; break;
				case 7: $out = "15"; break;
			}
		if ( !$out )
			return "\xf";
		return "\3$out";
	}
	
	/**
	 * \brief Get a 12 bit hex string (useful to convert to Darkplaces)
	 */
	function to12bit()
	{
		$m = $this->bright ? 15 : 5;
		$r = $m * !!( $this->code & self::RED );
		$g = $m * !!( $this->code & self::GREEN );
		$b = $m * !!( $this->code & self::BLUE );
		return dechex($r).dechex($g).dechex($b);
	}
	
	/**
	 * \brief Get the Darkplaces color code 
	 * \note Null color results in an empty string
	 */
	function dp()
	{
		if ( !$this->code )
			return "";
		if ( $this->bright )
		{
			switch ( $this->code )
			{
				case 5: return "^6";
				case 6: return "^5";
				default: return "^{$this->code}";
			}
		}
		else
			return "^x".$this->to12bit();
	}
	
	static $qfont_table = array(
	'',   ' ',  '-',  ' ',  '_',  '#',  '+',  '·',  'F',  'T',  ' ',  '#',  '·',  '<',  '#',  '#', // 0
	'[',  ']',  ':)', ':)', ':(', ':P', ':/', ':D', '«',  '»',  '·',  '-',  '#',  '-',  '-',  '-', // 1
	'?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?', // 2 
	'?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?', // 3
	'?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?', // 4
	'?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?', // 5
	'?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?', // 6
	'?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?', // 7
	'=',  '=',  '=',  '#',  '¡',  '[o]','[u]','[i]','[c]','[c]','[r]','#',  '¿',  '>',  '#',  '#', // 8
	'[',  ']',  ':)', ':)', ':(', ':P', ':/', ':D', '«',  '»',  '#',  'X',  '#',  '-',  '-',  '-', // 9
	' ',  '!',  '"',  '#',  '$',  '%',  '&',  '\'', '(',  ')',  '*',  '+',  ',',  '-',  '.',  '/', // 10
	'0',  '1',  '2',  '3',  '4',  '5',  '6',  '7', '8',  '9',  ':',  ';',  '<',  '=',  '>',  '?',  // 11
	'@',  'A',  'B',  'C',  'D',  'E',  'F',  'G', 'H',  'I',  'J',  'K',  'L',  'M',  'N',  'O',  // 12
	'P',  'Q',  'R',  'S',  'T',  'U',  'V',  'W', 'X',  'Y',  'Z',  '[',  '\\', ']',  '^',  '_',  // 13
	'.',  'A',  'B',  'C',  'D',  'E',  'F',  'G', 'H',  'I',  'J',  'K',  'L',  'M',  'N',  'O',  // 14
	'P',  'Q',  'R',  'S',  'T',  'U',  'V',  'W', 'X',  'Y',  'Z',  '{',  '|',  '}',  '~',  '<'   // 15
	);
	
	// supports for up to 3 byte long UTF-8 characters
	static function dp_char_convert($string)
	{
		$out = "";
		
		$unicode = array();        
		$v = array();
		
		for ($i = 0; $i < strlen( $string ); $i++ ) 
		{
			$c = $string[$i];
			$o = ord($c);
			
			if ( $o < 128 ) 
				$out .= $c;
			else 
			{
			
				if ( count($v) == 0 )
				{
					$s = "";
					$length = ( $o < 224 ) ? 2 : 3;
				}
				
				$v[] = $o;
				$s .= $c;
				
				if ( count( $v ) == $length ) 
				{
					$unicode = ( $length == 3 ) ?
						( ( $v[0] % 16 ) << 12 ) + ( ( $v[1] % 64 ) << 6 ) + ( $v[2] % 64 ):
						( ( $v[0] % 32 ) << 6 ) + ( $v[1] % 64 );
					$out .= ( ($unicode & 0xFF00) == 0xE000 ) ? self::$qfont_table[$unicode&0xff] : $s;
					
					$v = array();
				}
			} 
		}
		return $out;
	}

}


/**
 * \brief Convert \c $msg in an IRC Action
 */
function irc_action($msg)
{
	return "\1ACTION $msg\1";
}