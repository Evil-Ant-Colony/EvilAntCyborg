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
 * \brief An IRC user
 */
class IRC_User
{
	public $nick;  ///< Nick
	public $host;  ///< Host
	public $name;  ///< Logged-in name
	public $channels=array();///< Channels this user is in
	
	function __construct($name,$nick=null,$host=null)
	{
		if ( !$nick && $name )
			$nick = $name;
		$this->nick = $nick;
		$this->host = $host;
		$this->name = $name;
	}
	
	/**
	 * \brief Check if \c $other can be trusted to be this user
	 *
	 * Will check the authed name, then host and finally nick.
	 * Values not set in this object will be ignored
	 */
	function check_trust(IRC_User $other)
	{
		if ( $this->name )
			return $this->name == $other->name;
		if ( $this->host )
			return $this->host == $other->host;
		if ( $this->nick )
			return $this->nick == $other->nick;
		return false;
	}
	
	function add_channel($chan)
	{
		if ( ! in_array($chan,$this->channels) )
			$this->channels[]= $chan;
	}
	
	function remove_channel($chan)
	{
		if ( ($key = array_search($chan, $this->channels)) !== false) 
			array_splice($this->channels,$key,1);
	}
}
