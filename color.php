<?php

/// \todo instead of just 7bit conversion use 8 bit conversion (1 bit for brightness)
class Color
{
	/**
	 * \brief Strip colors from an IRC colored string
	 */
	static function irc2none($string)
	{
		return preg_replace("{\x03([0-9][0-9]?)?(,[0-9][0-9]?)?}","",$string);
	}
	
	
	static function dp2irc($string)
	{
		return preg_replace_callback("/(\^\^)|(\^[0-9])|(\^x[0-9a-fA-F]{3})/",
			function ($matches)
			{
				if ( $matches[0] == "^^" )
					return "^";
				return self::oct2irc(self::dp2oct($matches[0]));
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
				return self::oct2ansi(self::dp2oct($matches[0]));
			}
			,$string)."\x1b[0m";
	}
	
	/**
	 * \brief Convert a 24bit hexadecimal color code to a 7bit color code
	 */
	private static function hex2oct($color)
	{
		$color = "$color";
		$r = $color[0] > '7';
		$g = $color[1] > '7';
		$b = $color[2] > '7';
		return $r|($g<<1)|($b<<2);
	}
	
	/**
	 * \brief Convert a 7bit color code to an ANSI escape sequence
	 * \note Converts black to white to display nicely on terminals with a black background
	 */
	private static function oct2ansi($color)
	{
		if ( $color == null )
			return "\x1b[0m";
		$color = (int)$color;
		if ( $color < 1 || $color > 7 )
			$color = 7;
		return "\x1b[3{$color}m";
	}
	
	private static function dp2oct($color)
	{
		$digit = 7;
		if ( strlen($color) == 2 ) // ^N
		{
			$digit = (int)$color[1];
			switch($digit)
			{
				case 5: $digit = 6; break;
				case 6: $digit = 5; break;
			}
		}
		else if ( strlen($color) == 5 ) // ^xNNN
			$digit = self::hex2oct(substr($color,2));
		return $digit;
	}
	
	private static function irc2oct($color)
	{
		switch((int)$color)
		{
			case 1:  // black
			case 14: // gray
				return 0;
			case 4: // red
			case 5: // dark red
				return 1;
			case 3: // dark green
			case 9: // green
				return 2;
			case 8: // yellow
			case 7: // orange
				return 3;
			case 2: // dark blue
			case 12: // blue
				return 4;
			case 6: // dark magenta
			case 13:// magenta
				return 5;
			case 10: // dark cyan
			case 11: // cyan
				return 6;
			case 0:  // white
			case 15: // light gray
				return 7;
			default: 
				return null;
		}
	}
	
	private static function oct2irc($color)
	{
		$out = "";
		switch((int)$color)
		{
			case 0: $out = 1; break;
			case 1: $out = 4; break;
			case 2: $out = 9; break;
			case 3: $out = 7; break;
			case 4: $out = 12; break;
			case 5: $out = 13; break;
			case 6: $out = 11; break;
			// no white
		}
		return "\x03$out";
	}
	
	
} 
