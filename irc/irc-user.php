<?php

class IRC_User
{
	public $nick;  ///< Nick
	public $host;  ///< Host
	public $name;  ///< Logged-in name
	public $channels=array();
	
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
