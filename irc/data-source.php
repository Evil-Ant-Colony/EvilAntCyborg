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
require_once("irc/irc-user.php");

/**
 * \brief Input taken from a data source (mainly designed for IRC
 */
class MelanoBotCommand
{
	public $bot;    ///< Bot instance that generated this command
	public $cmd;    ///< Explicit command for input like (Botnick: command options), null for "raw" messages
	public $params; ///< Array containing all the arguments (ie: input string broken into words)
	public $from;   ///< Nick of the user who generated this command
	public $host;   ///< IRC host of said user :nick!name@host
	public $channel;///< Channel (or array of channels) to send a reply to
	public $raw;    ///< Raw string that created this command
	public $irc_cmd;///< IRC command (PRIVMSG, JOIN etc)
	
	function __construct($cmd, $params, $from, $host, $channel, $raw, $irc_cmd)
	{
		$this->cmd = $cmd; 
		$this->params = $params; 
		$this->from = $from; 
		$this->host = $host; 
		$this->channel = $channel;
		$this->raw = $raw; 
		$this->irc_cmd = $irc_cmd;
	}
	
	/**
	 * \brief Get a string from the command arguments
	 * \param $cmd    whether to include \c $this->cmd
	 * \param $offset index to start from
	 * \param $length number of parameters to be included
	 */
	function param_string($cmd=false,$offset=0,$length=null)
	{
		$p = $this->params;
		if ( $cmd )
			array_unshift($p,$this->cmd);
		return implode(" ",array_slice($p,$offset,$length));
	}

}

/**
 * \brief Source of MelanoBotCommand objects
 */
abstract class DataSource
{
	abstract function initialize(BotData $data);
	function finalize(BotData $data){}
	/**
	 * \brief Get a command
	 * \note Best if not blocking
	 * \return A valid MelanoBotCommand or \b null if none is availble
	 */
	abstract function get_command();
}

/**
 * \brief Read commands from standard input
 */
class Stdin_Data_Source extends DataSource
{
	function initialize(BotData $data)
	{
		stream_set_blocking(STDIN,0);
		stream_set_timeout(STDIN,10);
		$data->add_to_list('owner',new IRC_User(null,':STDIN:',':STDIN:'));
	}
	
	function get_command()
	{
		$data = fgets(STDIN,512);
		if ( $data == "" )
			return null;
		$data_arr = explode(" ",trim($data));
		$cmd = array_shift($data_arr);
		Logger::log("std",">",$data,1);
		return new MelanoBotCommand($cmd,$data_arr,':STDIN:',':STDIN:',":STDIN:",$data,'PRIVMSG');
	}
}

/**
 * \brief Interface for classes that somehow communicate periodically with some external process
 */
interface ExternalCommunicator
{
	function initialize(BotData $data);
	function finalize(BotData $data);
	/**
	 * \brief Called periodically, can be used to send messages from the external source to IRC
	 */
	function step(MelanoBot $bot, BotData $data);
}

/**
 * \brief Data that can be shared among data sources, executors, dispatchers and communicators
 */
class BotData
{
	public $data = array();           ///< Misc data that can be shared between executors
	public $lists = array();          ///< Lists of user "list_name" => array(user_nick=>host or null)
	public $grant_access = array();   ///< Grant rights from a list to other list1 => array(list2begrantedrights)
	public $driver = null;            ///< BotDriver this data belongs to
	
	function __construct(BotDriver $driver)
	{
		$this->driver = $driver;
	}
	
	/**
	 * \brief Add/update an IRC user to a user list
	 */
	function add_to_list($list,IRC_User $user)
	{
		if ( !isset($this->lists[$list]) )
			$this->lists[$list] = array();
		$this->lists[$list][] = $user;
	}
	
	/**
	* \brief Remove a user from a list
	* \return FALSE if the user isn't in the list
	* \sa remove_from_list_nick()
	*/
	function remove_from_list($list,IRC_User $user)
	{
		if ( isset($this->lists[$list]) ) 
		{
			for( $i = 0; $i < count($this->lists[$list]); $i++ )
				if ( $this->lists[$list][$i]->check_trust($user) )
				{
					array_splice($this->lists[$list],$i,1);
					return true;
				}
		}
		return false;
	}
	
	/**
	 * \brief Remove a user from a list
	 * \note This function only checks for the user nick
	 * \return FALSE if the user isn't in the list
	 * \sa remove_from_list()
	 */
	function remove_from_list_nick($list,$nick)
	{
		if ( isset($this->lists[$list]) ) 
		{
			for( $i = 0; $i < count($this->lists[$list]); $i++ )
				if ( $this->lists[$list][$i]->nick == $nick )
				{
					array_splice($this->lists[$list],$i,1);
					return true;
				}
		}
		return false;
	}
	
	/**
	* \brief Check whether a user is in a list
	*/
	function user_in_list($list,IRC_User $user)
	{
		if ( isset($this->lists[$list]) )
		{
			foreach ( $this->lists[$list] as $l )
				if ( $l->check_trust($user) )
					return true;
		}
		
		if ( isset($this->grant_access[$list]) )
			foreach($this->grant_access[$list] as $l)
				if ( $this->user_in_list($l,$user) )
					return true;
		return false;
	}
	
	/**
	 * \brief Get an array of users which are seen by the bot and belong to the given list
	 */
	function active_users_in_list( MelanoBot $bot, $list )
	{
		$maybe = array();
		foreach($bot->all_users() as $user)
			if ( $this->user_in_list($list,$user) )
				$maybe[]= $user;
		return $maybe;
	}
	
	/**
	 * \brief Get all users who have granted the rights of the given list
	 */
	function all_users_in_list($list)
	{
		$users = array();
		if ( isset($this->lists[$list]) )
			$users = $this->lists[$list];
		if ( isset($this->grant_access[$list]) )
			foreach($this->grant_access[$list] as $access )
				$users = array_merge($users,$this->all_users_in_list($access));
		return $users;
	}

}