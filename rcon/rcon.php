<?php

require_once("misc/logger.php");

class Rcon_Server
{
	public $host, $port;
	
	function Rcon_Server($host="", $port="")
	{
		$this->host = $host;
		$this->port = $port;
	}
	
	public function __toString()
    {
        return "{$this->host}:{$this->port}";
    }
}

class Rcon_Packet
{
	public $contents, $payload, $server, $valid = false;
	
	static $read_header ="\xff\xff\xff\xffn";
	static $send_header="\xff\xff\xff\xff";
	// darkplaces/console.c: char log_dest_buffer[1400]; (NUL-terminated)
	const MAX_READ_LENGTH = 1399;
	
	function Rcon_Packet($payload=null, $server=null)
	{
		$this->payload = $payload;
		$this->server = $server;
		$this->valid = $payload != null;
		$this->contents = self::$send_header.$payload;
	}
	
	function send($socket)
	{
		socket_sendto($socket, $this->contents, strlen($this->contents), 0, 
			$this->server->host, $this->server->port);
	}
	
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

class Rcon
{
	public $read;
	public $write;
	public $password;
	//public $secure = 0;
	public $socket;
	
	function Rcon($host,$port,$password)
	{
		$this->write = new Rcon_Server ( $host, $port );
		$this->read = new Rcon_Server ( $host, $port );
		$this->password = $password;
		
		$this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
		socket_bind($this->socket, $host);
		socket_getsockname($this->socket,$this->read->host,$this->read->port);
	}
	
	
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
		$packet = new Rcon_Packet("rcon {$this->password} $command",$this->write);
		Logger::log("dp","<",Color::dp2ansi($command),0);
		$packet->send($this->socket);
		return $packet;
	}
	
	function read()
	{
		$packet = Rcon_Packet::read($this->socket,Rcon_Packet::MAX_READ_LENGTH);
		/*if ( $packet->valid && $packet->payload )
			Logger::log("dp",">",Color::dp2ansi($packet->payload),0);*/
		return $packet;
	}
	
	function connect()
	{
		Logger::log("dp","!","Connecting to {$this->read}",1);
		$this->send("addtolist log_dest_udp {$this->read->host}:{$this->read->port}");
	}
	
	function irc_name()
	{
		return ":RCON:{$this->write}";
	}
}

/*date_default_timezone_set("UTC");
$rcon = new Rcon ( "127.0.0.1", 26000, "foo");
$rcon->connect();
$rcon->send("say test");
while(true)
{
	$rcon->read();
}*/
