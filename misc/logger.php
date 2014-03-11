<?php
require_once("misc/color.php");


class Logger
{
	private static $instance = null;
	private $source_color = array();
	private $direction_color = array();
	public  $pad_size=3;
	public  $verbosity = 1;
	
	private function Logger() {}
	
	function register_source($id,$color)
	{
		if ( !is_object($color) )
			$color = new Color($color);
		$this->source_color[$id] = $color->ansi();
	}
	
	function register_direction($direction,$color)
	{
		if ( !is_object($color) )
			$color = new Color($color);
		$this->direction_color[$direction] = $color->ansi();
	}
	
	static function instance()
	{
		if ( !self::$instance )
			self::$instance = new Logger();
		return self::$instance;
	}
	
	static function log($source,$direction,$text,$verbosity=2)
	{
		self::instance()->source_log($source,$direction,$text,$verbosity);
	}
	
	function plain_log($text,$verbosity)
	{
		if ( $verbosity <= $this->verbosity )
			echo "\x1b[30;1m".date("[H:i:s]")."\x1b[0m".rtrim($text)."\n";
	}
	
	function source_log($source,$direction,$text,$verbosity)
	{
		$this->plain_log( 
			$this->source_color[$source].str_pad($source,$this->pad_size).
			$this->direction_color[$direction]."$direction\x1b[0m$text",
			 $verbosity );
	}
	
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
