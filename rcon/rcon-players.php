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

require_once("misc/logger.php");

/**
 * \brief A player seen on rcon
 */
class RconPlayer
{
	public $name; ///< Name (dp encoded)
	public $slot; ///< Player slot number (used by kick et all)
	public $ip;   ///< IP address/port
	public $id;   ///< Player id (used by most rcon output regarding the payer)
	public $ping; ///< Ping
	public $pl;   ///< Packet loss
	public $frags;///< Score
	public $time; ///< Time since they connected to the server
	static $geoip;///< Result of geoip_open (or null if GeoIP is disabled)
	
	/**
	 * \brief Get GeoIP record
	 * \note Works only for IPv4 for now
	 */
	function geoip_record()
	{
		if ( self::$geoip != null && $this->ip )
		{
			// get IP address without port
			$p = strpos($this->ip,':');
			$ip = $p ? substr($this->ip,0,$p) : $this->ip;
			
			return @geoip_record_by_addr(self::$geoip,$ip);
		}
		return null;
	}
	
	/**
	 * \brief Extract country name from the player's IP address
	 */
	function country()
	{
		if ( self::$geoip != null && $this->ip )
		{
			$p = strpos($this->ip,':');
			$ip = $p ? substr($this->ip,0,$p) : $this->ip;
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
