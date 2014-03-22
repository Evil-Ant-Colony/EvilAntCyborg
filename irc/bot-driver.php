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

require_once("irc/melanobot.php");
require_once("irc/executors/abstract.php");
require_once("misc/logger.php");

/**
 * \brief Handle the bot connection as well as external data sources and comminicators
 * 
 * Ensures that the bot is properly connected, 
 * receives commands from he bot or other sources and sends them to 
 * the dispatchers for execution
 */
class BotDriver
{
	public $bot;                      ///< IRC listener
	public $data = null;              ///< Shared bot data
	public $dispatchers = array();    ///< Dispatchers, they handle different executors on different sets of channels
	public $data_sources = array();   ///< Command sources other than the bot itself (eg: standard input)
	public $post_executors = array(); ///< List of executors applied before the bot quits
	public $pre_executors = array();  ///< List of executors applied before the bot starts
	public $filters = array();        ///< Stuff to be applied to each command before checking for execution
	public $extarnal_comm = array();  ///< Connect external processes to irc
	
	/**
	 * \brief Install data source
	 * \todo Uniform install function like the dispacher has
	 */
	function install_source(DataSource $source)
	{
		$this->data_sources []= $source;
	}
	
	/**
	 * \brief Install a single dispatcher or an array of them
	 */
	function install($dispatchers)
	{
		if ( !is_array($dispatchers) )
			$this->dispatchers []= $dispatchers;
		else
			$this->dispatchers = array_merge($this->dispatchers,$dispatchers);
	}
	
	
	/**
	 * \brief Append executors to the list
	 * These executors are executed after the bot has disconnected
	 */
	function install_post_executor($ex)
	{
		if ( !is_array($ex) )
			$this->post_executors []= $ex;
		else
			$this->post_executors = array_merge($this->post_executors,$ex);
	}
	/**
	 * \brief Append executors to the list
	 * These executors are executed before the bot has connected
	 */
	function install_pre_executor($ex)
	{
		if ( !is_array($ex) )
			$this->pre_executors []= $ex;
		else
			$this->pre_executors = array_merge($this->pre_executors,$ex);
	}
	
	/**
	 * \brief Install global filters, applied to a command before being forwarded to the dispatchers
	 */
	function install_filter($ex)
	{
		if ( !is_array($ex) )
			$this->filters []= $ex;
		else
			$this->filters = array_merge($this->filters,$ex);
	}
	
	/**
	 * \brief Install external communicator
	 */
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
	
	
	/**
	 * \brief Check if the filters allow the execution of the given command
	 * \return \b false if at least a filter has discarded the message, \b true if it can be executed
	 */
	function filter(MelanoBotCommand $cmd)
	{
		foreach($this->filters as $f )
		{
			if(!$f->check($cmd,$this->bot,$this->data))
				return false;
		}
		return true;
		
	}
	
	/**
	 * \brief A step in the process loop
	 * 
	 * Execute communicators, get a command and forward it.
	 */
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
	
	/**
	 * \brief Check the bot connection status
	 * \return \b false if the loop has to be stopped, \b true if commands can be processed
	 */
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
	
	/**
	 * \brief Run the bot
	 *
	 * Initializes resources, while the bot is connected executes commands, then finalize
	 */
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