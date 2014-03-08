<?php

require_once("melanobot.php");
require_once("executors-abstract.php");

class BotData
{
	public $data = array();           ///< Misc data that can be shared between executors
	public $lists = array();          ///< Lists of user "list_name" => array(user_nick=>host or null)
	public $grant_access = array();   ///< Grant rights from a list to other list1 => array(list2begrantedrights)

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
	public $dispatcher;
	
	function BotDriver(MelanoBot $bot)
	{
		$this->bot = $bot;
		stream_set_blocking(STDIN,0);
		stream_set_timeout(STDIN,10);
		$this->data = new BotData;
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
			$this->bot->log(print_r($cmd,true),3);
			$this->dispatcher->loop_step($cmd,$this->bot,$this->data);
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
		
		$this->dispatcher->loop_begin($this->bot,$this->data);
		
		while($this->check_status())
		{
			$this->loop_step();
		}
		
		$this->dispatcher->loop_end($this->bot,$this->data);
		
	}
}