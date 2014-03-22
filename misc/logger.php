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
 
require_once("misc/color.php");

/**
 * \brief Singleton class to display some nicely formatted output
 * \note It shows a time before each output line, so you need to set the timezone to avoid PHP warnings
 */
class Logger
{
	private static $instance = null;    ///< Singleton instance
	private $source_color = array();    ///< Array source => Color
	private $direction_color = array(); ///< Array direction => Color
	public  $pad_size=3;                ///< String length for the source identifier
	public  $verbosity = 1;             ///< Controls which messages have to be shown, messages with higher verbosity levels won't be shown
	
	private function __construct() {}

	/**
	 * \brief Register a source name and its color
	 * \sa log(), source_log()
	 */
	function register_source($id,$color)
	{
		if ( !is_object($color) )
			$color = new Color($color);
		$this->source_color[$id] = $color->ansi();
	}
	
	/**
	 * \brief Register a direction name and its color
	 *
	 * A direction is to tell whether the log line represent data sent to the 
	 * source (<), received from it (>) or a general information (!)
	 *
	 * \sa log(), source_log()
	 */
	function register_direction($direction,$color)
	{
		if ( !is_object($color) )
			$color = new Color($color);
		$this->direction_color[$direction] = $color->ansi();
	}
	
	/**
	 * \brief Get the singleton
	 */
	static function instance()
	{
		if ( !self::$instance )
			self::$instance = new Logger();
		return self::$instance;
	}
	
	/**
	 * \brief Basic logging function
	 *
	 * Calls <tt>instance()->source_log()</tt>, static to make the code less verbose
	 *
	 * \sa plain_log(), source_log()
	 */
	static function log($source,$direction,$text,$verbosity=2)
	{
		self::instance()->source_log($source,$direction,$text,$verbosity);
	}
	
	/**
	 * \brief Simple log, output time and the text
	 * \param $text Output line
	 * \param $verbosity If greater than <tt>$this->verbosity</tt>, it will be discarded
	 * \sa log(), source_log()
	 */
	function plain_log($text,$verbosity)
	{
		if ( $verbosity <= $this->verbosity )
			echo "\x1b[30;1m".date("[H:i:s]")."\x1b[0m".rtrim($text)."\n";
	}
	
	/**
	 * \brief Logging function
	 *
	 * The output contains a colored string for \c $source and \c $direction
	 *
	 * \sa plain_log(), log()
	 */
	function source_log($source,$direction,$text,$verbosity)
	{
		$this->plain_log( 
			$this->source_color[$source].str_pad($source,$this->pad_size).
			$this->direction_color[$direction]."$direction\x1b[0m$text",
			 $verbosity );
	}
	
	/**
	 * \brief Register the default source/directions
	 *
	 * Sources: irc, dp, std.
	 * Directions: < > !
	 */
	function default_settings()
	{
		$this->register_direction("<",2);
		$this->register_direction(">",3);
		$this->register_direction("!",4);
		$this->register_source("irc",5);
		$this->register_source("dp",6);
		$this->register_source("std",new Color(7,true));
	}
}
