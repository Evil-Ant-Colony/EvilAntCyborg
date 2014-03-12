<?php

require_once("irc/melanobot.php");
require_once("irc/executors/abstract.php");
require_once("misc/logger.php");

/**
 * \brief Get and execute commands
 */
class BotDriver
{
	public $bot;                      ///< IRC listener
	public $data = null;              ///< Shared bot data
	public $dispatchers = array();
	public $data_sources = array();
	public $post_executors = array(); ///< List of executors applied before the bot quits
	public $pre_executors = array();  ///< List of executors applied before the bot starts
	public $filters = array();        ///< Stuff to be applied to each command before checking for execution
	public $extarnal_comm = array();///< Connect external processes to irc
	
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
	
	
	/// Append an executor to the list
	function install_post_executor($ex)
	{
		if ( !is_array($ex) )
			$this->post_executors []= $ex;
		else
			$this->post_executors = array_merge($this->post_executors,$ex);
	}
	/// Append an executor to the list
	function install_pre_executor($ex)
	{
		if ( !is_array($ex) )
			$this->pre_executors []= $ex;
		else
			$this->pre_executors = array_merge($this->pre_executors,$ex);
	}
	
	function install_filter($ex)
	{
		if ( !is_array($ex) )
			$this->filters []= $ex;
		else
			$this->filters = array_merge($this->filters,$ex);
	}
	
	function install_external($ex)
	{
		if ( !is_array($ex) )
			$this->extarnal_comm []= $ex;
		else
			$this->extarnal_comm = array_merge($this->extarnal_comm,$ex);
	}
	
	function __construct(MelanoBot $bot)
	{
		$this->bot = $bot;
		$this->data = new BotData($this);
		$this->data->grant_access['admin'] = array('owner');
	}
	
	
	function filter(MelanoBotCommand $cmd)
	{
		foreach($this->filters as $f )
		{
			if(!$f->check($cmd,$this->bot,$this->data))
				return false;
		}
		return true;
		
	}
	
	function loop_step()
	{
		foreach ( $this->extarnal_comm as $c )
			$c->step($this->bot,$this->data);
		
		foreach ( $this->data_sources as $src )
		{
			$cmd = $src->get_command();
				
			if ( $cmd != null && $this->filter($cmd) )
			{
				Logger::instance()->plain_log(print_r($cmd,true),4);
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
			
		foreach ( $this->extarnal_comm as $c )
			$c->initialize($this->data);
			
		// initialize channel list
		$chans = $this->bot->join_list;
		foreach($this->dispatchers as $disp)
			if ( is_array($disp->channel_filter) )
				$chans = array_merge($chans,$disp->channel_filter);
		/// \todo skip fake channels (rcon)
		$this->bot->join_list = array_unique($chans);
		
		
		foreach ( $this->pre_executors as $ex )
			$ex->execute($this->bot,$this->data);
		
		/// \todo parametrize
		$delay = 0.001;
		// loop
		while($this->check_status())
		{
			$time = microtime(true);
			
			$this->loop_step();
			
			$this->bot->buffer->flush_nonblock(0,$delay*1000000);
			
			// computer programs are weird, they need to get some sleep if they have not been awake long enough :3
			$delta = microtime(true) - $time;
			if ( $delta < $delay )
				usleep(($delay-$delta)*1000000);
		}
		
		foreach ( $this->post_executors as $ex )
			$ex->execute($this->bot,$this->data);
		
		// finalize sources
		foreach ( $this->data_sources as $src )
			$src->finalize($this->data);
			
		array_shift($this->data_sources);
		
		foreach ( $this->extarnal_comm as $c )
			$c->finalize($this->data);
	}
}