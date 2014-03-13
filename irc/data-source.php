<?php

require_once("misc/logger.php");
require_once("irc/irc-user.php");

class MelanoBotCommand
{
	public $cmd, $params, $from, $host, $channel, $raw,  $irc_cmd;
	
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
	
	function param_string($cmd=false,$offset=0,$length=null)
	{
		$p = $this->params;
		if ( $cmd )
			array_unshift($p,$this->cmd);
		return implode(" ",array_slice($p,$offset,$length));
	}

}

abstract class DataSource
{
	abstract function initialize(BotData $data);
	function finalize(BotData $data){}
	abstract function get_command();
}

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

interface ExternalCommunicator
{
	function initialize(BotData $data);
	function finalize(BotData $data);
	function step(MelanoBot $bot, BotData $data);
}

class BotData
{
	public $data = array();           ///< Misc data that can be shared between executors
	public $lists = array();          ///< Lists of user "list_name" => array(user_nick=>host or null)
	public $grant_access = array();   ///< Grant rights from a list to other list1 => array(list2begrantedrights)
	public $driver = null;
	
	function __construct(BotDriver $driver)
	{
		$this->driver = $driver;
	}
	
	/// Add/update an IRC user to a user list
	function add_to_list($list,IRC_User $user)
	{
		if ( !isset($this->lists[$list]) )
			$this->lists[$list] = array();
		$this->lists[$list][] = $user;
	}
	
	/**
	* \brief Remove a user from a list
	* \return FALSE if the user isn't in the list
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
	
	function active_users_in_list( MelanoBot $bot, $list )
	{
		$maybe = array();
		foreach($bot->all_users() as $user)
			if ( $this->user_in_list($list,$user) )
				$maybe[]= $user;
		return $maybe;
	}

}