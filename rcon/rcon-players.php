<?php

require_once("misc/logger.php");

class RconPlayer
{
	public $name;
	public $slot;
	public $ip;
	public $id;
	public $ping;
	public $pl;
	public $frags;
	public $time;
	
	function is_bot()
	{
		return substr($this->ip,0,3) == 'bot';
	}
	
	function normalize()
	{
		if ( isset($this->id) )
			$this->id = (int)$this->id;
		if ( isset($this->slot) )
			$this->slot = self::str2slot($this->slot);
	}
	
	function merge(RconPlayer $other)
	{
		if ( isset($other->slot) && $other->slot != $this->slot )
			return $this;
			
		if ( isset($other->name) ) $this->name = $other->name;
		if ( isset($other->id) ) $this->id = $other->id;
		if ( isset($other->ip) ) $this->ip = $other->ip;
		if ( isset($other->ping) ) $this->ping = $other->ping;
		if ( isset($other->pl) ) $this->pl = $other->pl;
		if ( isset($other->frags) ) $this->frags = $other->frags;
		if ( isset($other->time) ) $this->time = $other->time;
		
		return $this;
	}
	
	static function str2slot($slot) { return (int)trim($slot,'#'); }
	
}

class PlayerManager
{
	private $players=array();
	private $count_players = 0;
	private $count_bots = 0;
	public $max = 0;
	
	function add(RconPlayer $player)
	{
		$player->normalize();
		if ( !$player->slot )
			return;

		Logger::log("dp","!","\x1b[32mAdding\x1b[0m player #\x1b[31m{$player->slot}\x1b[0m ".
			Color::dp2ansi($player->name)." @ \x1b[36m$player->ip\x1b[0m",3);
			
		if ( isset($this->players[$player->slot]) )
		{
			$this->players[$player->slot]->merge($player);
		}
		else
		{
			if ( $player->is_bot() )
				$this->count_bots++;
			else
				$this->count_players++;
			$this->players [$player->slot] = $player;
		}
	}
	function find_by_id ( $id )
	{
		foreach ( $this->players as $player ) 
			if ( $id == $player->id )
				return $player;
		return null;
	}
	
	function find ( $slot )
	{
		$slot = RconPlayer::str2slot($slot);
		if ( isset($this->players[$slot]) )
			return $this->players[$slot];
		return null;
	}
	
	function set_players($players)
	{
		$old = $this->players;
		$this->clear();
		foreach($players as $player)
		{
			$player->normalize();
			if ( isset($old[$player->slot]) )
				$this->add($old[$player->slot]->merge($player));
			else
				$this->add($player);
		}
	}
	
	/**
	 * \brief Remove player by id and return a reference to it
	 */
	function remove ( $slot )
	{
		$slot = RconPlayer::str2slot($slot);
		if ( isset($this->players[$slot]) )
		{
			$player = $this->players[$slot];
			Logger::log("dp","!","\x1b[31mRemoving\x1b[0m player #\x1b[31m{$player->slot}\x1b[0m ".
				Color::dp2ansi($player->name)." @ \x1b[36m$player->ip\x1b[0m",3);
			unset($this->players[$slot]);
			if ( $player->is_bot() )
				$this->count_bots--;
			else
				$this->count_players--;
			return $player;
		}
		return null;
	}
	
	function clear()
	{
		$this->players = array();
		$this->count_bots = $this->count_players = 0;
	}
	
	function count_players()
	{
		return $this->count_players;
	}
	
	function count_bots()
	{
		return $this->count_bots;
	}
	
	function count_all()
	{
		return $this->count_players + $this->count_bots;
	}
	
	function all()
	{
		return array_values($this->players);
	}
	
	
	function all_no_bots()
	{
		$players = array();
		foreach($this->players as $slot => $player)
			if ( $player && !$player->is_bot())
				$players[]=$player;
		return $players;
	}
}
