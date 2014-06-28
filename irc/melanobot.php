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

require_once("misc/color.php");
require_once("irc/data-source.php");
require_once("misc/logger.php");
require_once("misc/list.php");

/**
 * \brief Non-blocking TCP connection
 */
class MelanoBotServer
{
	public $server; ///< Address or host name
	public $port;   ///< Port number
	private $socket;///< Internal connection socket
	
	
	function __construct($server, $port=6667)
	{
		$this->server = $server;
		$this->port = $port;
		$this->socket = false;
	}
	
	/**
	 * \brief Conver to the string "host:port"
	 */
	public function __toString()
	{
		return "{$this->server}:{$this->port}";
	}
	
	/**
	 * \brief Begin a TCP connection
	 */
	function connect()
	{
		$this->socket = fsockopen($this->server,$this->port);
		if ( $this->socket !== false )
		{
			stream_set_blocking($this->socket,0);
			stream_set_timeout($this->socket,1);
		}
		return $this->socket;
	}
	
	/**
	 * \brief Check whether the server is connected
	 */
	function connected()
	{
		return !($this->socket === false || feof($this->socket));
	}
	
	/**
	 * \brief End the connection
	 */
	function disconnect()
	{
		if ( $this->socket )
		{
			fclose($this->socket);
			$this->socket = false;
		}
	}
	
	/**
	 * \brief Send some data to the server
	 */
	function write($data)
	{
		fputs($this->socket,$data);
	}
	
	/**
	 * \brief Read data from the server
	 * \param $len Maximum length
	 */
	function read($len=512)
	{
		return fgets($this->socket,$len);
	}
}

/**
 * \brief Buffer messages to an IRC server preventing flooding
 */
class BotOutBuffer
{
	public $flood_time_start = 0.2; ///< Minimum delay between messages (in seconds)
	public $flood_time_max = 2;     ///< Maximum delaw between messages
	public $flood_max_bytes = 512;  ///< Maximum nuber of bytes in a message (longer messages will be truncated)
	public $server = null;          ///< MelanoBotServer to send the data to
	public $flood_max_delay = 12;   ///< Messages older than this many seconds will be discarded
	public $dicard_threshold = 1;   ///< Messages with at least this priority won't be discarded
	private $flood_time_counter = 1;///< Internal counter to increase delay between messages
	private $flood_next_time = 0;   ///< When it will be possible to send the next message (in seconds)
	private $buffer = null;         ///< Store messages when it's not possible to send them
	
	function __construct()
	{
		$this->buffer = new StablePriorityQueue;
		//$this->buffer->max_size = 32;
	}
	
	/// Maximum number of messages in the buffer
	function max_size()
	{
		return $this->buffer->max_size;
	}
	function set_max_size($n)
	{
		$this->buffer->max_size = $n;
	}
	
	/**
	 * \brief Number of microseconds it has to wait before sending a message
	 * if the value is negative, it can send right now
	 */
	function can_send_in()
	{
		return ( $this->flood_next_time - microtime(true) ) * 1000000;
	}
	
	/**
	 * \brief Block and sleep (if needed) and send data
	 */
	private function send_wait($message)
	{
		$wait = $this->can_send_in();
		
		if ( $wait > 0 )
			usleep( $wait );
		else
			$this->flood_time_counter = 1;

		$this->send_raw($message);
	}
	
	/**
	 * \brief Send data only if it can
	 * \return \c true if data has been sent
	 */
	private function send_no_wait($message)
	{
		if ( $this->can_send_in() > 0 )
			return false;
		
		if ( $this->flood_time_counter > 1 )
			$this->flood_time_counter--;
			
		$this->send_raw($message);
		
		return true;
	}
	
	
	/**
	 * \brief Send data if it can
	 * \param $message Message to send
	 * \param $microseconds Maximum sleeping time
	 * \return \c true if data has been sent
	 */
	private function send_wait_some($message,$microseconds)
	{
		$wait = $this->can_send_in();
		
		if ( $wait > 0 && $wait < $microseconds)
			usleep( $wait );
		else if ( $wait > 0 )
			return false;
		else if ( $this->flood_time_counter > 1 )
			$this->flood_time_counter--;

		$this->send_raw($message);
		
		return true;
	}
	
	/**
	 * \brief Schedule data to be sent
	 *
	 *  May not send right away to avoid flooding
	 *
	 * \return \c false if the message can't be sent (full buffer)
	 */
	function send($data, $priority)
	{
		$message = new StdClass;
		$message->data = $data;
		$message->time = microtime(true);
		$message->priority = $priority;
		if ( !$this->send_no_wait($message) )
		{
			$this->buffer->push($message,$priority);
		}
		return true;
	}
	
	/**
	 * \brief Send a message immediately
	 * \note Increases internal time counter for the next message
	 */
	private function send_raw($message)
	{
		if ( $this->flood_max_delay && 
				$message->time + $this->flood_max_delay < microtime(true) &&
				$message->priority < $this->dicard_threshold )
		{
			Logger::log("irc","!","\x1b[31mDISCARDING MESSAGE\x1b[0m ".Color::irc2ansi($message->data),0);
			$this->flood_next_time = microtime(true) + $this->flood_time_start;
			return;
		}
		Logger::log("irc","<",Color::irc2ansi($message->data),0);
		$data = substr($message->data,0,$this->flood_max_bytes-2)."\r\n";
		$this->server->write($data);
		$wait = $this->flood_time_start*$this->flood_time_counter;
		$this->flood_next_time = microtime(true) + max($this->flood_time_max,$wait);
		$this->flood_time_counter++;
	}
	
	/**
	 * \brief Flush at most \c $count buffered messages ensuring they are sent
	 * \param $count Number of messages to be sent, if 0 send all
	 */
	function flush_block($count=0)
	{
		if ( $count <= 0 )
			$count = count($this->buffer);
		else
			$count = min($count,count($this->buffer));
			
		for ( $i = 0; $i < $count; $i++ )
		{
			$this->send_wait($this->buffer->pop());
		}
	}
	
	
	/**
	 * \brief Flush at most \c $count buffered messages while it doesn't require too much sleeping
	 * \param $count Number of messages to be sent, if 0 send all
	 * \param $microseconds Maximum sleeping time
	 * \return Number of sent messages
	 */
	function flush_nonblock($count=0,$microseconds=0)
	{
		if ( $count <= 0 )
			$count = count($this->buffer);
		else
			$count = min($count,count($this->buffer));
			
		for ( $i = 0; $i < $count; $i++ )
		{
			$message = $this->buffer->top();
			if ( !$this->send_wait_some($message,$microseconds) )
				return $i;
			$this->buffer->pop();
		}
		if ( $this->can_send_in() <= 0 && $this->flood_time_counter > 1 )
			$this->flood_time_counter--;

		return $i;
	}
	
}

/**
* \brief IRC Bot connection
*/
class MelanoBot extends DataSource
{

	static $client_name = "MelanoBot";
	static $version     = "v1";
	static $source_url  = "https://github.com/mbasaglia/Simple_IRC_Bot/";

	const DISCONNECTED        = 0; ///< Bot is not conneced
	const SERVER_CONNECTED    = 1; ///< Connected to a server but the IRC protocol has not been initialized
	const PROTOCOL_CONNECTING = 2; ///< Connected to a server and establishing the IRC protocol
	const PROTOCOL_CONNECTED  = 3; ///< IRC fully connected, can send data


	private $server_index = 0;///< Internal index to the currently connected server
	public $servers;      ///< Array of server
	public $real_name;    ///< IRC bot real name
	public $nick;         ///< Current bot NICK
	public $auth_nick;    ///< Nick to use during AUTH
	public $password;     ///< AUTH password
	public $listen_to;    ///< Base for explicit commands, defaults to "$nick:"
	public $modes = null; ///< String for MODE +
	private $connection_status = self::DISCONNECTED; 
	private $users = array();  ///< Internal structure handling users seen by the bot (see below for access to this data)
	public $join_list =array();///< List of channels scheduled to be joined
	public $strip_colors=false;///< Whether IRC colors should be removed before command interpretation
	public $auto_restart=false;///< Whether the bot always restarts on network quit. \todo only used elsewhere, maybe worth moving to data
	private $channels =array();///< Channels the bot is currently connected to
	public $buffer; ///< Buffer message to the server
	public $connection_password = null; ///< PASS password
	
	/**
	 * \brief Create a bot
	 * \param $servers  MelanoBotServer instance or array
	 * \param $nick     Bot nick name
	 * \param $password Bot AUTH password
	 * \param $channels Array of channels to join on startup
	 */
	function __construct($servers, $nick, $password, $channels )
	{
		if ( !is_array($servers) )
			$this->servers = array($servers);
		else
			$this->servers = $servers;
		$this->real_name = $nick;
		$this->auth_nick = $nick;
		$this->nick = $nick;
		$this->password = $password;
		$this->join_list = $channels;
		$this->listen_to = "$nick:";
		$this->buffer = new BotOutBuffer();
		$this->connect_cycle();
	}
	
	/**
	 * \brief Connect to the $i^th server
	 */
	function connect($i = 0)
	{
		if ( isset($this->servers[$i]) )
		{
			$this->servers[$i]->connect();
			if ( $this->servers[$i]->connected() )
			{
				$this->connection_status = self::SERVER_CONNECTED;
				$this->server_index = $i;
				$this->buffer->server = $this->servers[$i];
				Logger::log("irc","!","Connected to {$this->buffer->server}",1);
			}
			else
			{
				Logger::log("irc","!","Connection failed ".$this->servers[$i]."",1);
			}
		}
	}

	/**
	 * \brief Get the server the bot is connected to
	 */
	function current_server()
	{
		return $this->servers[$this->server_index];
	}
	
	/**
	 * \brief Disconnect from server
	 */
	function disconnect()
	{
		$this->channels = array();
		$this->users = array();
		if ( $this->buffer->server->connected() )
		{
			Logger::log("irc","!","Disconnecting {$this->buffer->server}",1);
			$this->buffer->server->disconnect();
		}
		$this->connection_status = self::DISCONNECTED;
	}

	/**
	 * \brief Connect to the first available server
	 * \return \b true on success
	 */
	private function connect_cycle()
	{
		$this->connection_status = self::DISCONNECTED;
		$i = $this->server_index;
		for ( $tries = 0; $tries < count($this->servers); $tries++ )
		{
			$i = ( $i + 1 ) % count($this->servers);
			$this->connect($i);
			if ( $this->servers[$i]->connected() )
			{
				return true;
			}
		}
		Logger::log("irc","!","All connections failed",1);
		return false;
	}

	/**
	 * \brief Quit and disconnect from current server and try to cycle server looking for a connection
	 */
	function reconnect($message="reconnect")
	{
		$this->quit($message);
		$join_list = array_merge($this->channels,$this->join_list);
		if ( $this->connect_cycle() )
			$this->join_list = $join_list;
	}
	
	/**
	 * \brief Add a channel to the internal structure
	 */
	private function add_channel($chan)
	{
		$this->channels []= $chan;
		$this->channels = array_unique($this->channels);
	}
	
	/**
	 * \brief Remove a channel from the internal structure
	 */
	private function remove_channel($chan)
	{
		if (($key = array_search($chan, $this->channels)) !== false) 
		{
			array_splice($this->channels,$key,1);
		}
		foreach($this->users_in_channel($chan) as $user)
			$this->remove_user_from_channel($chan,$user);
	}
	
	/**
	 * \brief send a request to change the nick
	 */
	function set_nick($nick)
	{
		$this->command('NICK',$nick);
	}
	
	/**
	 * \brief Apply the new nick (after the server has accepted it)
	 */
	private function apply_nick($nick)
	{
		$this->change_nick($this->nick, $nick);
		$this->nick = $nick;
		$this->listen_to = "$nick:";
		Logger::log("irc","!","Nick changed to $nick");
	}
	
	/**
	 * \brief Get a list of users seen by the bot in the given channel
	 */
	function users_in_channel($channel)
	{
		$list = array();
		foreach($this->users as $u)
			if ( in_array($channel,$u->channels) )
				$list []= $u;
		return $list;
	}
	
	/**
	 * \brief Get a reference to an existing user or create a new object if not found
	 */
	function get_user($nick,$host=null)
	{
		$user = $this->find_user_by_nick($nick,$host);
		if ( $user )
			return $user;
		return new IRC_User(null,$nick,$host);
	}
	
	/**
	 * \brief Find a user by nick (and optionally host)
	 * \return A reference to the found user or null if that user is not know to the bot
	 */
	function find_user_by_nick($nick,$host=null)
	{
		foreach ( $this->users as $user )
			if ( ( $host && $user->host == $host ) || $user->nick == $nick )
				return $user;
		return null;
	}
	
	/**
	 * \brief Update the user structure to keep track that a user has joined a channel
	 */
	private function add_user_to_channel($chan,$nick,$host)
	{
		$u = $this->find_user_by_nick($nick,$host);
		if ( !$u )
		{
			$u = new IRC_User(null,$nick,$host);
			$this->users []= $u;
		}
		else
		{
			// ensure data is consistent
			$u->nick = $nick;
			$u->host = $host;
		}
		$u->add_channel($chan);
		Logger::log("irc","!","\x1b[36m$nick \x1b[32mjoined\x1b[0m $chan");
	}
	
	/**
	 * \brief Remove a user from the bot's data structure
	 */
	private function remove_user(IRC_User $user)
	{
		for ( $i = 0; $i < count($this->users); $i++ )
			if ( $this->users[$i]->check_trust($user) )
			{
				array_splice($this->users,$i,1);
				Logger::log("irc","!","\x1b[36m{$user->nick}\x1b[0m has been \x1b[31mremoved\x1b[0m");
				return;
			}
	}
	
	function all_users()
	{
		return $this->users;
	}
	
	/**
	 * \brief Update the user data structure, removing user from the channel
	 * \note If the user is no longer connected to any channel known by the bot, this user is removed
	 */
	private function remove_user_from_channel($chan,IRC_User $user)
	{
		$user->remove_channel($chan);
		Logger::log("irc","!","\x1b[36m{$user->nick} \x1b[31mparted\x1b[0m $chan");
		if ( empty($user->channels) )
			$this->remove_user($user);
			
	}
	
	/**
	 * \brief Update user nick
	 */
	private function change_nick($old,$new)
	{
		$u = $this->find_user_by_nick($old);
		if ( $u )
		{
			$u->nick = $new;
			Logger::log("irc","!","Updated nick ($old->$new)");
			return $u;
		}
		return null;
	}
	
	/**
	 * \brief Set up IRC USER and NICK and update connection status
	 */
	function login()
	{
		if ( $this->connection_status == self::SERVER_CONNECTED )
		{
			$this->login_ext($this->real_name,$this->nick);
			$this->connection_status = self::PROTOCOL_CONNECTING;
		}
	}
	
	/**
	 * \brief Set up IRC USER and NICK
	 */
	private function login_ext($real_name, $nick)
	{
		if ( $this->connection_password )
			$this->internal_command('PASS', $this->connection_password);
		$this->internal_command('USER',"$nick localhost $nick :$real_name");
		$this->internal_command('NICK', $nick);
	}
	
	/**
	 * \brief AUTH and set MODEs
	 */
	function auth()
	{
		if ( $this->password && $this->auth_nick )
		{
			$this->internal_command('AUTH', $this->auth_nick." ".$this->password);
			if ( strlen($this->modes) > 0 )
				$this->internal_command('MODE', $this->nick." +".$this->modes);
		}
	}
	
	/**
	 * \brief Execute an irc command
	 * \param $command  IRC command like PRIVMSG, JOIN etc.
	 * \param $data     Parameters to the command (as a string)
	 * \param $priority Messages with higher priority may be sent before than others
	 * \see say() for a simpler interface to PRIVMSG
	 */
	function command($command, $data, $priority=0)
	{
		if ( $this->buffer->server->connected() )
		{
			$data = str_replace(array("\n","\r")," ",$data);
			$this->buffer->send("$command $data",$priority);
		}
	}
	
	/**
	 * \brief Execute a command right away
	 */
	private function internal_command($command,$data)
	{
		if ( $this->buffer->server->connected() )
		{
			$data = str_replace(array("\n","\r")," ",$data);
			Logger::log("irc","<","\x1b[31m$command\x1b[0m $data",0);
			$this->buffer->server->write("$command $data\r\n");
		}
	}
	
	/**
	 * \brief JOIN a list of channels
	 * \param $channels A single channel string or an array
	 */
	function join($channels)
	{
		if ( !is_array($channels) )
			$channels = array($channels);
		
			
		//$this->channels = array_unique(array_merge($this->channels,$channels));
		foreach($channels as $channel)
		{
			if (($key = array_search($channel,$this->join_list)) !== false)
				array_splice($this->join_list,$key,1);
			$this->command('JOIN',$channel,256);
		}
	}
	
	/**
	 * \brief IRC QUIT and disconnect
	 */
	function quit($message="bye!")
	{
		$this->internal_command('QUIT',":$message");
		if ( $this->connection_status > self::SERVER_CONNECTED )
			$this->connection_status = self::SERVER_CONNECTED;
		$this->disconnect();
	}
	
	/// noop, abstract override
	function initialize(BotData $data) {}
	
	/**
	 * \brief Get a MelanoBotCommand
	 * \return A valid command or \b null if there's no command available
	 */
	function get_command()
	{
		if ( !$this->buffer->server->connected() )
		{
			$this->connection_status = self::DISCONNECTED;
			Logger::log("irc","!","Network Quit on {$this->buffer->server}",1);
			$this->reconnect("Automatic Reconnection");
			return null;
		}
		
		if ( $this->connection_status >= self::PROTOCOL_CONNECTED && !empty($this->join_list) )
		{
			$this->join($this->join_list);
		}
		
		$data = $this->buffer->server->read();
		
		if ( $data == "" )
			return null;
		
		Logger::log("irc",">",Color::irc2ansi($data),0);
		
		if ( $this->strip_colors )
			$data = Color::irc2none($data);
		
		$inarr = explode(' ',trim($data));
		$insize = count($inarr);
		if ( $inarr[0] == 'PING' )
		{
			$this->internal_command('PONG',$inarr[1]);
		}
		else if ( $inarr[0] == 'ERROR' )
		{
			if ( strripos($data,'throttled') !== FALSE )
			{
				$this->reconnect("Throttled");
				return null;
			}
			Logger::log("irc","!","\x1b[31m$data\x1b[0m",1);
		}
		
		if ( $insize > 1 && $inarr[1] == 001 )
		{
			$this->connection_status = self::PROTOCOL_CONNECTED;
			$this->auth();
			
		}
		
		
		if ( $insize > 5 && $inarr[1] == 353 )
		{
			$chan = $inarr[4];
			foreach ( $this->users as $u )
				$u->remove_channel($chan);
			
			$nicks = array();
			for ( $i = 5; $i < $insize; $i++ )
			{
				$nick = trim($inarr[$i],"\n\r:+@");
				$nicks[] = $nick;
				$u = $this->add_user_to_channel($chan,$nick,null);
			}
			return new MelanoBotCommand(353,$nicks,null,null,$chan,$data,353);
				
		}
		else if ( $insize > 1 && $inarr[1] == 433 )
		{
			$newnick = "{$this->nick}_";
			$this->set_nick($newnick);
			$this->apply_nick($newnick);
		}

		$from = substr(strstr($inarr[0],"!",true),1);
		$from_host = substr(strstr($inarr[0],"@"),1);
		
		if ( $insize > 1 )
		{
			$irc_cmd = $inarr[1];
			$chan = $insize > 2 ? $inarr[2] : null;
			
			switch($irc_cmd)
			{
				case 'JOIN':
					$chan = trim($chan,":");
					$this->add_user_to_channel($chan,$from,$from_host);
					if ( $from == $this->nick )
						$this->add_channel($chan);
					return new MelanoBotCommand($irc_cmd, array($from), /*$from,*/ $from, $from_host, $chan, $data, $irc_cmd);
				case 'KICK':
					$who = $insize > 3 ? $inarr[3] : null;
					if ( $who == $this->nick )
					{
						$this->join_list []= $chan;
						$this->remove_channel($chan);
					}
					else if ( $who != null && $user = $this->find_user_by_nick($who) )
						$this->remove_user_from_channel($chan,$user);
					$message = trim(substr($data,strpos($data,':',1)+1));
					return new MelanoBotCommand($irc_cmd, array($who, $message), $from, $from_host, $chan, $data, $irc_cmd);
				
				case 'PART':
					if ( $from == $this->nick )
						$this->remove_channel($chan);
					else if ( $user = $this->find_user_by_nick($from,$from_host) )
						$this->remove_user_from_channel($chan,$user);
					$message = trim(substr($data,strpos($data,':',1)+1));
					return new MelanoBotCommand($irc_cmd, array($message), $from, $from_host, $chan, $data, $irc_cmd);
				case 'NICK':
					$nick = trim($inarr[2],"\n\r!:");
					$chans = array();
					if ( $from == $this->nick )
					{
						$this->apply_nick($nick);
						$chans = $this->channels;
					}
					else if ( $user = $this->change_nick($from, $nick) )
						$chans = $user->channels;
					return new MelanoBotCommand($irc_cmd, array($nick), $from, $from_host, $chans, $data, $irc_cmd);
					break;
				case 'QUIT':
					$user = $this->find_user_by_nick($from,$from_host);
					$chans = array();
					if ( $user )
					{
						$chans = $user->channels;
						$this->remove_user($user);
					}
					if ( $from == $this->nick )
						$this->channels = array();
					$message = trim(substr($data,strpos($data,':',1)+1));
					return new MelanoBotCommand($irc_cmd, array($message), /*$from,*/ $from, $from_host, $chans, $data, $irc_cmd);
				case 'NOTICE':
					$query = trim(substr($data,strpos($data,':',1)+1));
					return new MelanoBotCommand($irc_cmd,array($query), $from, $from_host, null, $data, $irc_cmd);
				case 'PRIVMSG':
					$query = trim(substr($data,strpos($data,':',1)+1));
					$query_params = explode(' ',$query);
					if ( $insize > 3 )
					{
						$inarr[3] = ltrim($inarr[3],":");
						$inarr[$insize-1] = rtrim($inarr[$insize-1]);
						
						if ( $from == $this->nick )
						{
							Logger::log("irc","!","Got a message from myself",1);
						}
						else if ( $from != "" && ( $chan == $this->nick || $inarr[3] == $this->listen_to ) )
						{
							// update the host/nick for the user issuing the command
							/// \todo decide whether it's useful to perform this with every input and not just direct PRIVMSG
							$user = $this->find_user_by_nick($from,$from_host);
							if ( !$user )
								$user = new IRC_User($from,$from,$from_host);
							else
							{
								$user->nick = $from;
								$user->host = $from_host;
							}
							
							if ( $chan == $this->nick )
								$chan = $from;
							if ( $inarr[3] == $this->listen_to )
								array_shift($query_params);
								
							while ( $query_params[0] == '' && !empty($query_params) )
								array_shift($query_params);
							
							$command = strtolower(trim(array_shift($query_params),"!"));
							
							$n_params=count($query_params);
							/*$chanhax = false;
							if ( $n_params > 2 && $query_params[$n_params-2] == 'chanhax' )
							{
								$chan = array_pop($query_params);
								$chanhax = true;
								array_pop($query_params); // remove chanhax
							}
							$query = implode(' ',$query_params);*/
								
							return new MelanoBotCommand($command,$query_params,$from,$from_host,$chan,$data, $irc_cmd);
						}
					}
					return new MelanoBotCommand(null,$query_params,$from,$from_host,$chan,$data, $irc_cmd);
				default:
					return new MelanoBotCommand($irc_cmd,array_slice($inarr,2),$from,$from_host,null,$data, $irc_cmd);
					
			}
		}
		
		
		return null;
		
		
	}
	
	/**
	 * \brief Send a message to the given channel (or user)
	 * \see command() for generic IRC commands
	 */
	function say($channel,$msg,$priority=0)
	{
		if ( strlen($msg) == 0 )
			return;
		if ( $channel != $this->nick )
		{
			$this->command("PRIVMSG","$channel :$msg",$priority);
		}
		else
			Logger::log("irc","!","ERROR: trying to send a message to myself",1);
	}
	
	/**
	 * \brief Check whether the bot is connected to a server
	 */
	function server_connected()
	{
		return $this->buffer->server && $this->buffer->server->connected();
	}
	
	/**
	 * \brief Get connection status
	 */
	function connection_status()
	{
		if ( !$this->server_connected() )
			return self::DISCONNECTED;
		return $this->connection_status;
	}

	
}