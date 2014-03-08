<?php

require_once("melanobot.php");
require_once("executors-abstract.php");

/**
 * \brief Get and execute commands
 */
class BotDriver
{
	public $bot;                      ///< IRC listener
	public $data = null;              ///< Shared bot data
	public $dispatchers = array();
	public $data_sources = array();
	
	function install_source(DataSource $source)
	{
		$this->data_sources []= $source;
	}
	
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
		$this->data = new BotData($this);
		$this->data->grant_access['admin'] = array('owner');
	}
	
	function loop_step()
	{
		foreach ( $this->data_sources as $src )
		{
			$cmd = $src->get_command();
				
			if ( $cmd != null )
			{
				/// \todo apply filters here (eg: blacklist)
				$this->bot->log(print_r($cmd,true),4);
				foreach($this->dispatchers as $disp)
				{
					if ( $disp->loop_step($cmd,$this->bot,$this->data) )
						break;
				}
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
		
		// initialize sources
		foreach ( $this->data_sources as $src )
			$src->initialize($this->data);
			
		array_unshift($this->data_sources,$this->bot);
		
		/// \todo move Executors Pre and Post in here (no need for the dispatcher)
		/// Maybe merge those classes in a single one and have different install functions
			
		// initialize dispatchers
		$chans = $this->bot->join_list;
		foreach($this->dispatchers as $disp)
		{
			$disp->loop_begin($this->bot,$this->data);
			
			if ( is_array($disp->channel_filter) )
				$chans = array_merge($chans,$disp->channel_filter);
		}
		$this->bot->join_list = array_unique($chans);
		
		// loop
		while($this->check_status())
		{
			$this->loop_step();
		}
		
		// finalize dispatchers
		foreach($this->dispatchers as $disp)
			$disp->loop_end($this->bot,$this->data);
		
		// finalize sources
		foreach ( $this->data_sources as $src )
			$src->finalize($this->data);
			
		array_shift($this->data_sources);
		
	}
}