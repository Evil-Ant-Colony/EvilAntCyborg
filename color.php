<?php

/// \todo instead of just 7bit conversion use 8 bit conversion (1 bit for brightness)
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
	
	function Color($code, $bright=false)
	{
		$this->code = (int)$code;
		$this->bright = $bright;
	}
	
	function r()
	{
		return !!($code & 1);
	}
	
	function g()
	{
		return !!($code & 2);
	}
	
	
	function b()
	{
		return !!($code & 4);
	}
	
// string conversion
	/**
	 * \brief Strip colors from an IRC colored string
	 */
	static function irc2none($string)
	{
		return preg_replace("{\x03([0-9][0-9]?)?(,[0-9][0-9]?)?}","",$string);
	}
	
	static function irc2ansi($string)
	{
		
		return preg_replace_callback("{\x03([0-9][0-9]?)?(,[0-9][0-9]?)?}",
			function ($matches)
			{
				if ( count($matches) > 1 )
					return Color::from_irc($matches[1])->ansi();
				return "";
			},$string);
	}
	
	static function dp2irc($string)
	{
		return preg_replace_callback("/(\^\^)|(\^[0-9])|(\^x[0-9a-fA-F]{3})/",
			function ($matches)
			{
				if ( $matches[0] == "^^" )
					return "^";
				return Color::from_dp($matches[0])->irc();
			}
			,$string)."\x03";
	}
	
	/**
	 * \brief Strip colors from a DP colored string
	 */
	static function dp2none($string)
	{
		return preg_replace_callback("/(\^\^)|(\^[0-9])|(\^x[0-9a-fA-F]{3})/",
			function ($matches)
			{
				if ( $matches[0] == "^^" )
					return "^";
				return "";
			}
			,$string);
	}
	
	/**
	 * \brief Convert a colored DP string to a colored ANSI string
	 */
	static function dp2ansi($string)
	{
		return preg_replace_callback("/(\^\^)|(\^[0-9])|(\^x[0-9a-fA-F]{3})/",
			function ($matches)
			{
				if ( $matches[0] == "^^" )
					return "^";
				return Color::from_dp($matches[0])->ansi();
			}
			,$string)."\x1b[0m";
	}

// static constructors
	/**
	 * \brief Create a color from a 3 digit hex string
	 */
	static function from_24hex($color)
	{
		$color = "$color";
		if ( strlen($color) < 3 )
			return new Color(self::NOCOLOR);
		$r = hexdec($color[0]); $g = hexdec($color[1]); $b = hexdec($color[2]);
		$rt = $r > 4; $gt = $g > 4; $bt = $b > 4;
		return new Color($rt|($gt<<1)|($bt<<2), $r > 7 || $b > 7 || $g > 7);
	}
	
	/**
	 * \brief Create a color from a DP ^string
	 */
	static function from_dp($color)
	{
		if ( strlen($color) == 2 ) // ^N
		{
			switch((int)$color[1])
			{
				case 5: return new Color(6);
				case 6: return new Color(5);
			}
		}
		else if ( strlen($color) == 5 ) // ^xNNN
			return self::from_24hex(substr($color,2));
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
	 * \brief Convert a 7bit color code to an ANSI escape sequence
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

	function irc()
	{
		$out = "";
		if ( $this->bright )
			switch($this->code)
			{
				case 0: $out = 14; break;
				case 1: $out = 4; break;
				case 2: $out = 9; break;
				case 3: $out = 7; break; // no bright yellow
				case 4: $out = 12; break;
				case 5: $out = 13; break;
				case 6: $out = 11; break;
				// no white
			}
		else
			switch($this->code)
			{
				case 0: $out = 1; break;
				case 1: $out = 5; break;
				case 2: $out = 3; break;
				case 3: $out = 7; break;
				case 4: $out = 2; break;
				case 5: $out = 6; break;
				case 6: $out = 10; break;
				case 7: $out = 15; break;
			}
		return "\x03$out";
	}

}
