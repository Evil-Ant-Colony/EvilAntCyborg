<?php

require_once("melanobot.php");
require_once("executors-abstract.php");

class BotData
{
	public $data = array();           ///< Misc data that can be shared between executors
	public $lists = array();          ///< Lists of user "list_name" => array(user_nick=>host or null)
	public $grant_access = array();   ///< Grant rights from a list to other list1 => array(list2begrantedrights)
	public $driver = null;
	
	function BotData(BotDriver $driver)
	{
		$this->driver = $driver;
	}
	
	/// Add/update an IRC user to a user list
	function add_to_list($list,$nick,$host=null)
	{
		if ( !isset($this->lists[$list]) )
			$this->lists[$list] = array();
		$this->lists[$list][$nick] = $host;
	}
	
	/**
	 * \brief Remove a user from a list
	 * \return FALSE if the user isn't in the list
	 */
	function remove_from_list($list,$nick)
	{
		if ( isset($this->lists[$list]) && array_key_exists($nick,$this->lists[$list]) ) 
		{
			unset($this->lists[$list][$nick]);
			return true;
		}
		return false;
	}
	
	/**
	 * \brief Check whether a user is in a list
	 */
    function user_in_list($list,$nick,$host)
    {
		if ( isset($this->lists[$list]) )
		{
			foreach ( $this->lists[$list] as $l_nick => $l_host )
			{
				if ( $l_host == null )
				{
					if ( $l_nick == $nick )
						return true;
				}
				else if ( $l_host == $host ) 
					return true;
			}
		}
		
		if ( isset($this->grant_access[$list]) )
			foreach($this->grant_access[$list] as $l)
				if ( $this->user_in_list($l,$nick,$host) )
					return true;
		return false;
	}

}

/**
 * \brief Get and execute commands
 */
class BotDriver
{
	public $bot;                      ///< IRC listener
	public $data = null;              ///< Shared bot data
	public $dispatchers = array();
	
	function install($dispatchers)
	{
		if ( !is_array($dispatchers) )
			$this->dispatchers []= $dispatchers;
		else
			$this->dispatchers = array_merge($this->dispatchers,$dispatchers);
	}
	
	function BotDriver(MelanoBot $bot)
	{
		$this->bot = $bot;
		stream_set_blocking(STDIN,0);
		stream_set_timeout(STDIN,10);
		$this->data = new BotData($this);
		$this->data->grant_access['admin'] = array('owner');
		$this->data->add_to_list('owner',':STDIN:',':STDIN:');
	}
			
	function read_stdin()
	{
		$data = fgets(STDIN,512);
		if ( $data == "" )
			return null;
		$data_arr = explode(" ",trim($data));
		$cmd = array_shift($data_arr);
		return new MelanoBotCommand($cmd,$data_arr,':STDIN:',':STDIN:',"",$data,'PRIVMSG');
	}
	
	function loop_step()
	{
		$cmd = $this->bot->loop_step();
		if ( $cmd == null )
			$cmd = $this->read_stdin();
			
		if ( $cmd != null )
		{
			$this->bot->log(print_r($cmd,true),4);
			foreach($this->dispatchers as $disp)
			{
				if ( $disp->loop_step($cmd,$this->bot,$this->data) )
					break;
			}
		}
	}
	
	function check_status()
	{
		switch ( $this->bot->connection_status() )
		{
			case MelanoBot::DISCONNECTED:
				return false;
			case MelanoBot::SERVER_CONNECTED:
				$this->bot->login();
				return true;
			case MelanoBot::PROTOCOL_CONNECTING:
			case MelanoBot::PROTOCOL_CONNECTED:
				return true;
			default:
				return false;
		}
	}
	
	function run()
	{
		if ( !$this->bot )
			return;
			
		$chans = $this->bot->join_list;
		foreach($this->dispatchers as $disp)
		{
			$disp->loop_begin($this->bot,$this->data);
			
			if ( is_array($disp->channel_filter) )
				$chans = array_merge($chans,$disp->channel_filter);
		}
		$this->bot->join_list = array_unique($chans);
		
		while($this->check_status())
		{
			$this->loop_step();
		}
		
		foreach($this->dispatchers as $disp)
			$disp->loop_end($this->bot,$this->data);
		
	}
}