<?php

class Color
{
	/**
	 * \brief Strip colors from an IRC colored string
	 */
	static function irc2none($string)
	{
		return preg_replace("{\x03([0-9][0-9]?)?(,[0-9][0-9]?)?}","",$string);
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
	
	
} 
