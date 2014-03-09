<?php

require_once("logger.php");
require_once("color.php");

class RconPlayer
{
	public $nick;
	public $slot;
	public $ip;
	public $id;
	
	function is_bot()
	{
		return $this->ip == 'bot';
	}
}

class PlayerManager
{
	private $players=array();
	public $count = 0;
	public $max = 0;
	
	function add(RconPlayer $player)
	{
		$player->id = (int) $player->id;
		Logger::log("dp","!","\x1b[32mAdding\x1b[0m player \x1b[31m{$player->id}\x1b[0m #\x1b[31m{$player->slot}\x1b[0m ".
			Color::dp2ansi($player->nick)." @ \x1b[36m$player->ip\x1b[0m",3);
		$this->count++;
		$this->players [$player->id] = $player;
	}
	
	function find ( $id )
	{
		$id = (int)$id;
		if ( isset($this->players[$id]) )
			return $this->players[$id];
		return null;
	}
	
	/**
	 * \brief Remove player by slot and return a reference to it
	 */
	function remove ( $id )
	{
		$id = (int)$id;
		if ( isset($this->players[$id]) )
		{
			$player = $this->players[$id];
			Logger::log("dp","!","\x1b[31mRemoving\x1b[0m player \x1b[31m{$player->id}\x1b[0m #\x1b[31m{$player->slot}\x1b[0m ".
				Color::dp2ansi($player->nick)." @ \x1b[36m$player->ip\x1b[0m",3);
			unset($this->players[$id]);
			$this->count--;
			return $player;
		}
		return 0;
	}
	
	function clear()
	{
		$this->players = array();
		$this->count = 0;
	}
	
	function all()
	{
		$players = array();
		foreach($this->players as $id => $player)
			if ( $player )
				$players[]=$player;
		return $players;
	}
}
