<?php

require_once("misc/logger.php");

/**
 * \brief A player seen on rcon
 */
class RconPlayer
{
	public $name; ///< Name (dp encoded)
	public $slot; ///< Player slot (used by kick at all)
	public $ip;   ///< IP address/port
	public $id;   ///< Player id (used by most rcon output regarding the payer)
	public $ping; ///< Ping
	public $pl;   ///< Packet loss
	public $frags;///< Score
	public $time; ///< Time since they connected to the server
	static $geoip;///< Whether GeoIP is installed
	
	/**
	 * \brief Extract country name from the player's IP address
	 */
	function country()
	{
		if ( self::$geoip && $this->ip )
		{
			$p = strpos($this->ip,':');
			$ip = $p ? substr($this->ip,0,$p) : $this->ip;
			if ( self::$geoip === true )
				return @geoip_country_name_by_name($ip);
			return @geoip_country_name_by_addr(self::$geoip,$ip);
		}
		return "";
	}
	
	/**
	 * \brief Whether the player is a human or a bot client
	 */
	function is_bot()
	{
		return substr($this->ip,0,3) == 'bot';
	}
	
	/**
	 * \brief Convert id and slot to integers
	 */
	function normalize()
	{
		if ( isset($this->id) )
			$this->id = (int)$this->id;
		if ( isset($this->slot) )
			$this->slot = self::str2slot($this->slot);
	}
	
	/**
	 * \brief Merge information from another player into this one
	 * \return \b $this
	 */
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
	
	/**
	 * \brief Convert from #1 into (int)1
	 */
	static function str2slot($slot) { return (int)trim($slot,'#'); }
	
}

/**
 * \brief Manages players connected to a DP server
 */
class PlayerManager
{
	private $players=array();  ///< Connected clients
	private $count_players = 0;///< Number of actual playes
	private $count_bots = 0;   ///< Number of bots
	public $max = 0;           ///< Maximum number of players
	
	/**
	 * \brief Add a player
	 * \note If a player on the same slot exists, it is merged with \c $player
	 */
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
	
	/**
	 * \brief Get the player with the given id
	 * \return \b null if no such player exists
	 */
	function find_by_id ( $id )
	{
		foreach ( $this->players as $player ) 
			if ( $id == $player->id )
				return $player;
		return null;
	}
	
	/**
	 * \brief Get the player on the given slot
	 * \return \b null if no such player exists
	 */
	function find ( $slot )
	{
		$slot = RconPlayer::str2slot($slot);
		if ( isset($this->players[$slot]) )
			return $this->players[$slot];
		return null;
	}
	
	/**
	 * \brief Set the player list
	 *
	 * Already existsing players will be merged, new ones added, extra ones removed
	 */
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
	
	/**
	 * \brief Remove all players
	 */
	function clear()
	{
		$this->players = array();
		$this->count_bots = $this->count_players = 0;
	}
	
	/**
	 * \brief Number of real clients
	 */
	function count_players()
	{
		return $this->count_players;
	}
	
	/**
	 * \brief Number of bot clients
	 */
	function count_bots()
	{
		return $this->count_bots;
	}
	
	/**
	 * \brief Total number (bots*real)
	 */
	function count_all()
	{
		return $this->count_players + $this->count_bots;
	}
	
	/**
	 * \brief Get all players
	 */
	function all()
	{
		return array_values($this->players);
	}
	
	/**
	 * \brief Get all the players who aren't bots
	 */
	function all_no_bots()
	{
		$players = array();
		foreach($this->players as $slot => $player)
			if ( $player && !$player->is_bot())
				$players[]=$player;
		return $players;
	}
}
