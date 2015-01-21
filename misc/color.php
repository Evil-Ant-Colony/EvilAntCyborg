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
 * \brief Store a color code and convert colored strings (4 bit color depth)
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
	static function dp_char_convert($string)
	{
		$out = "";
		
		$unicode = array();        
		$bytes = array();
		
		for ($i = 0; $i < strlen( $string ); $i++ ) 
		{
			$c = $string[$i];
			$char_byte = ord($c);
			
			if ( $char_byte < 128 )
			{
				// ASCII
				$out .= $c;
			}
			else 
			{
				if ( count($bytes) == 0 )
				{
					// Start of multibyte character
					$unicode_char = "";
					$length = 0;
					// extract number of leading 1s
					while ( $char_byte & 0x80 )
					{
						$length++;
						$char_byte <<= 1;
					}
					
					// Must be at least 110..... or fail
					if ( $length < 2 )
						continue;
					
					// Restore byte (leading 1s have been eaten off)
					$char_byte >>= $length;
				}
				
				// Keep track of bytes
				$bytes[] = $char_byte;
				$unicode_char .= $c;
				
				// Reached the end
				if ( count( $bytes ) == $length )
				{
					$unicode = 0;
					foreach ( $bytes as $byte )
					{
						// Add up all the bytes 
						// Besides the first, they all start with 01... 
						// So they give 6 bytes and need to be &-ed with 63
						$unicode <<= 6;
						$unicode |= $byte & 63;
					}
					
					// Get the output string we want
					$out .= ( ($unicode & 0xFF00) == 0xE000 ) ? self::$qfont_table[$unicode&0xff] : $unicode_char;
					
					$bytes = array();
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



/**
 * \brief Simple, 12 bit rgb color
 */
class Color_12bit
{
	public $r, $g, $b;
	
	function __construct ($r=0, $g=0, $b=0)
	{
		$this->r = $r;
		$this->g = $g;
		$this->b = $b;
	}
	
	/**
	 * \brief Get the 12bit integer
	 */
	function bitmask()
	{
		return ($this->r<<8)|($this->g<<4)|$this->b;
	}
	
	/**
	 * \brief Encode to darkplaces
	 */
	function encode()
	{
		switch ( $this->bitmask() )
		{
			case 0x000: return "^0";
			case 0xf00: return "^1";
			case 0x0f0: return "^2";
			case 0xff0: return "^3";
			case 0x00f: return "^4";
			case 0xf0f: return "^6";
			case 0x0ff: return "^5";
			case 0xfff: return "^7";
		}
			
		return "^x".dechex($this->r).dechex($this->g).dechex($this->b);
	}
	
	/**
	 * \brief Decode darkplaces color
	 */
	static function decode($dpcolor)
	{
		$dpcolor = ltrim($dpcolor,"^x");
		
		if ( strlen($dpcolor) == 3 )
			return new Color_12bit(hexdec($dpcolor[0]),hexdec($dpcolor[1]),hexdec($dpcolor[2]));
		else if ( strlen($dpcolor) == 1 )
			switch ( $dpcolor[0])
			{
				case 0: return new Color_12bit(0,0,0);
				case 1: return new Color_12bit(0xf,0,0);
				case 2: return new Color_12bit(0,0xf,0);
				case 3: return new Color_12bit(0xf,0xf,0);
				case 4: return new Color_12bit(0,0,0xf);
				case 5: return new Color_12bit(0,0xf,0xf);
				case 6: return new Color_12bit(0xf,0,0xf);
				case 7: return new Color_12bit(0xf,0xf,0xf);
				case 8:
				case 9: return new Color_12bit(0x8,0x8,0x8);
			}
		return new Color_12bit();
	}
	
	/**
	 * \brief Blend two colors together
	 * \param $c1 First color
	 * \param $c2 Second color
	 * \param $factor Blend factor 0 => \c $c1, 1 => \c $c2
	 */
	static function blend(Color_12bit $c1, Color_12bit $c2, $factor)
	{
		return new Color_12bit(round($c1->r*(1-$factor) + $c2->r*$factor),
		                       round($c1->g*(1-$factor) + $c2->g*$factor),
		                       round($c1->b*(1-$factor) + $c2->b*$factor));
	}
	
	/**
	 * \brief Get a color from HSV components in [0,1]
	 */
	static function from_hsv($h,$s,$v)
	{
		
		$h *= 6;
		$c = $v*$s;
		$m = $v-$c;
		
		$h1 = floor($h);
		$f = $h - $h1;
		
		$n = $v - $c * $f;
		$k = $v - $c * (1 - $f);
		
		$v = round($v*0xf);
		$m = round($m*0xf);
		$n = round($n*0xf);
		$k = round($k*0xf);
		
		switch ($h1) 
		{
			case 0: return new Color_12bit($v,$k,$m);
			case 1: return new Color_12bit($n,$v,$m);
			case 2: return new Color_12bit($m,$v,$k);
			case 3: return new Color_12bit($m,$n,$v);
			case 4: return new Color_12bit($k,$m,$v);
			case 6:
			case 5: return new Color_12bit($v,$m,$n);
		}
		return new Color_12bit();
	}
}