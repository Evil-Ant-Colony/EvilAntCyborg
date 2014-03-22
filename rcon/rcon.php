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

/**
 * \brief Simple class holding host and port
 */
class Rcon_Server
{
	public $host, $port;
	
	function __construct($host="", $port="")
	{
		$this->host = $host;
		$this->port = $port;
	}
	
	public function __toString()
    {
        return "{$this->host}:{$this->port}";
    }
}

/**
 * \brief A Rcon packet
 *
 * This class hanldes sending/receiving the packet and extracting its contents
 */
class Rcon_Packet
{
	public $contents; ///< UDP packet payload (ie: header+payload)
	public $payload;  ///< Meaningful string to send to/receive from rcon
	public $server;   ///< Server this packet belongs to
	public $valid = false; ///< Whether it's a valid packet
	
	static $read_header ="\xff\xff\xff\xffn";
	static $send_header="\xff\xff\xff\xff";
	/// \note This 1399 values comes from darkplaces/console.c: <tt>char log_dest_buffer[1400];</tt> (NUL-terminated)
	const MAX_READ_LENGTH = 1399;
	
	function __construct($payload=null, $server=null)
	{
		$this->payload = $payload;
		$this->server = $server;
		$this->valid = $payload != null;
		$this->contents = self::$send_header.$payload;
	}
	
	/**
	 * \brief Send to the given socket
	 */
	function send($socket)
	{
		socket_sendto($socket, $this->contents, strlen($this->contents), 0, 
			$this->server->host, $this->server->port);
	}
	
	/**
	 * \brief Read mostly \c $len from the socket
	 * \note Remove $len and use MAX_READ_LENGTH directly
	 */
	static function read($socket, $len)
	{
		$packet = new Rcon_Packet();
		$packet->server = new Rcon_Server();
		@socket_recvfrom($socket, $packet->contents, $len, MSG_DONTWAIT, $packet->server->host, $packet->server->port);
		$head = substr($packet->contents,0,strlen(self::$read_header));
		$packet->payload = rtrim(substr($packet->contents,strlen(self::$read_header)),"\n\r");
		$packet->valid = $head == self::$read_header;
		return $packet;
	}
}

/**
 * \brief Low-level class to communicate through rcon
 */
class Rcon
{
	public $read;     ///< Read server (log_dest_udp)
	public $write;    ///< Write server
	public $password; ///< Password to the write server
	//public $secure = 0;
	public $socket;   ///< Socket used for communications
	
	function __construct($host,$port,$password)
	{
		$this->write = new Rcon_Server ( $host, $port );
		$this->read = new Rcon_Server ( $host, $port );
		$this->password = $password;
		
		$this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
		socket_bind($this->socket, $host);
		socket_getsockname($this->socket,$this->read->host,$this->read->port);
	}
	
	/**
	 * \brief Send the given command to rcon
	 */
	function send($command) 
	{
		/// \todo secure
		/*$payload = "";
		if( $this->secure > 1)
		{
			$payload = "getchallenge";
		}
		else if ( $this->secure == 1 )
		{
			$t = sprintf("%ld.%06d", time(), rand(0, 1000000));
			$key = hash_hmac("md4","$t $command", $this->password );
			$payload = "srcon HMAC-MD4 TIME $key $t $command";
		}
		else
			$payload = "rcon {$this->password} $command";*/
		Logger::log("dp","<",Color::dp2ansi($command),0);
		$packet = new Rcon_Packet("rcon {$this->password} $command",$this->write);
		$packet->send($this->socket);
		return $packet;
	}
	
	/**
	 * \brief Read a packet from rcon
	 */
	function read()
	{
		$packet = Rcon_Packet::read($this->socket,Rcon_Packet::MAX_READ_LENGTH);
		return $packet;
	}
	
	/**
	 * \brief Ensure that the socket receives output from rcon
	 */
	function connect()
	{
		$this->send("addtolist log_dest_udp {$this->read->host}:{$this->read->port}");
	}
}

/*
$rcon = new Rcon ( "127.0.0.1", 26000, "foo");
$rcon->connect();
$rcon->send("say test");
while(true)
{
	$packed = $rcon->read();
	echo "{$packet->payload}\n";
}*/
