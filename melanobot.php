<?php
require_once("color.php");
require_once("data-source.php");
require_once("logger.php");

function irc_action($msg)
{
	return "\x01ACTION $msg\x01";
}


class MelanoBotServer
{
	public $server, $port;
	private $socket;
	 
	
	function MelanoBotServer($server, $port=6667)
	{
		$this->server = $server;
		$this->port = $port;
		$this->socket = false;
	}
	
	public function __toString()
    {
        return "{$this->server}:{$this->port}";
    }
	
	function connect()
	{
		$this->socket = fsockopen($this->server,$this->port);
		//stream_set_blocking($this->socket,0);
		stream_set_timeout($this->socket,1);
		return $this->socket;
	}
	
	function connected()
	{
		return !($this->socket === false || feof($this->socket));
	}
	
	function disconnect()
	{
		if ( $this->socket )
		{
			fclose($this->socket);
			$this->socket = false;
		}
	}
	
	function write($data)
	{
		fputs($this->socket,$data);
	}
	
	function read($len=512)
	{
		return fgets($this->socket,$len);
	}
}

class BotOutBuffer
{
	public $flood_time_start = 0.2;
	private $flood_time_counter = 1;
	private $flood_next_time = 0;
	public $flood_max_bytes = 512;
	public $server = null;
	private $buffer = array();
	private $buffer_max_size = 128;
	
	function send($data)
	{
		$time = microtime(true);
		/// \todo buffer
		if ( $time < $this->flood_next_time )
			usleep(($this->flood_next_time - $time) * 1000000 );
		else
			$this->flood_time_counter = 1;

		$time = microtime(true);
		$this->flood_next_time = $time + $this->flood_time_start * $this->flood_time_counter;
		$this->flood_time_counter++;
		
		/*echo "Wait: ".($this->flood_next_time-$time).
			", Time: $time, Next: {$this->flood_next_time}, #{$this->flood_time_counter}\n";*/
		
		$this->write($data);
	}
	
	private function write($data)
	{
		$data = substr($data,0,$this->flood_max_bytes-2)."\n\r";
		$this->server->write($data);
	}
	
}

class MelanoBot extends DataSource
{

	const DISCONNECTED = 0;
	const SERVER_CONNECTED = 1;
	const PROTOCOL_CONNECTING = 2;
	const PROTOCOL_CONNECTED = 3;


	private $server_index;
    public $servers, $real_name, $nick, $auth_nick, $password;
    public $blacklist, $listen_to;
    public $mode = null;
    private $connection_status = self::DISCONNECTED; 
    private $names = array();
    public $join_list = array();
    public $strip_colors = false; ///< whether IRC colors should be removed before command interpretation
    public $auto_restart = false;
    public $channels=array(); ///< Channels the bot is currently connected to
    public $buffer;
    
    
    function MelanoBot($servers, $nick, $password, 
                 $channels, $blacklist=array())
    {
		if ( !is_array($servers) )
			$this->servers = array($servers);
		else
			$this->servers = $servers;
        $this->real_name = $nick;
        $this->auth_nick = $nick;
        $this->nick = $nick;
        $this->password = $password;
        $this->blacklist = $blacklist;
        $this->join_list = $channels;
        $this->listen_to = "$nick:";
        $this->buffer = new BotOutBuffer();
        $this->connect();
    }
    
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
    
    function disconnect()
    {
		$this->channels = array();
		$this->names = array();
		if ( $this->buffer->server->connected() )
		{
			Logger::log("irc","!","Disconnecting {$this->buffer->server}",1);
			$this->buffer->server->disconnect();
		}
		$this->connection_status = self::DISCONNECTED;
    }
    
    function reconnect($message="reconnect")
    {
		$this->connection_status = self::DISCONNECTED;
		$join_list = $this->channels;
		$this->quit($message);
		$i = $this->server_index;
		for ( $tries = 0; $tries < count($this->servers); $tries++ )
		{
			$i = ( $i + 1 ) % count($this->servers);
			$this->connect($i);
			if ( $this->servers[$i]->connected() )
			{
				$this->join_list = $join_list;
				return;
			}
		}
		Logger::log("irc","!","All connections failed",1);
		print_r($this);
    }
    
    function add_channel($chan)
    {
		$this->channels []= $chan;
		$this->channels = array_unique($this->channels);
    }
    
    function remove_channel($chan)
    {
		if (($key = array_search($chan, $this->channels)) !== false) 
		{
			array_splice($this->channels,$key,1);
		}
    }
    
    /// send a request to change the nick
    function set_nick($nick)
    {
        $this->command('NICK',$nick);
    }
    /// Apply the new nick (after the server has accepted it)
    private function apply_nick($nick)
    {
        $this->change_name($this->nick, $nick);
        $this->nick = $nick;
        $this->listen_to = "$nick:";
        Logger::log("irc","!","Nick changed to $nick");
    }
    
    /**
     * \brief find if $name is available
     * \return 0 if not found, 1 if found, 2 if found in $chan
     */
    function find_name($name,$chan)
    {
        if ( isset($this->names[$chan]) && in_array($name, $this->names[$chan]) )
            return 2;
        foreach($this->names as $ch => $names)
            if (  in_array($name, $names) )
                return 1;
        return 0;
    }
    
    private function add_name($chan,$name)
    {
        $this->names[$chan][]= $name;
        Logger::log("irc","!","Updated names for $chan (+$name)");
        Logger::log("irc","!",print_r($this->names[$chan],true));
    }
    
    private function remove_name($chan,$name)
    {
        if (($key = array_search($name, $this->names[$chan])) !== false) 
        {
            Logger::log("irc","!","Updated names for $chan (-$name)");
            array_splice($this->names[$chan],$key,1);
            Logger::log("irc","!",print_r($this->names[$chan],true));
        }
        else
        {
            Logger::log("irc","!","Not removing $name from $chan");
        }
    }
    
    private function change_name($name_old,$name_new)
    {
        foreach($this->names as $chan => &$names )
        {
            foreach($names as &$name)
                if ( $name == $name_old )
                    $name = $name_new;
        }
        Logger::log("irc","!","Updated names ($name_old->$name_new)");
        Logger::log("irc","!",print_r($this->names,true));
    }
    
    function login()
    {
        if ( $this->connection_status == self::SERVER_CONNECTED )
        {
			$this->login_ext($this->real_name,$this->nick);
			$this->connection_status = self::PROTOCOL_CONNECTING;
		}
    }
    
    function login_ext($real_name, $nick)
    {
        $this->command('USER',"$nick localhost $nick :$real_name");
        $this->command('NICK', $nick);
    }
    
    function auth()
    {
        if ( !is_null($this->password) && !is_null($this->auth_nick) )
        {
            $this->command('AUTH', $this->auth_nick." ".$this->password);
            if ( strlen($this->modes) > 0 )
                $this->command('MODE', $this->nick." +".$this->modes);
        }
    }
    
    function command($command, $data)
    {
        if ( $this->buffer->server->connected() )
        {
            $data = str_replace(array("\n","\r")," ",$data);
            $this->buffer->send("$command $data");
            Logger::log("irc","<",Color::irc2ansi("$command $data"),0);
        }
    }
    
    function join($channels)
    {
        if ( !is_array($channels) )
            $channels = array($channels);
        
            
        //$this->channels = array_unique(array_merge($this->channels,$channels));
        foreach($channels as $channel)
        {
            if (($key = array_search($channel,$this->join_list)) !== false)
                array_splice($this->join_list,$key,1);
            $this->command('JOIN',$channel);
        }
    }
    
    function quit($message="bye!")
    {
        $this->command('QUIT',":$message");
        if ( $this->connection_status > self::SERVER_CONNECTED )
			$this->connection_status = self::SERVER_CONNECTED;
        $this->disconnect();
    }
    
    
	function initialize(BotData $data){}
    
    function get_command()
    {
        if ( !$this->buffer->server->connected() )
        {
			$this->connection_status = self::DISCONNECTED;
            Logger::log("irc","!","Network Quit on {$this->buffer->server}",1);
            $this->reconnect("Automatic Reconnection");
            return null;
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
            $this->command('PONG',$inarr[1]);
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
        
        if ( $insize > 1 && $inarr[1] == 221  )
        {
            $this->connection_status = self::PROTOCOL_CONNECTED;
            $this->auth();
            
        }
        
		if ( $this->connection_status >= self::PROTOCOL_CONNECTED && !empty($this->join_list) )
		{
			$this->join($this->join_list);
		}
        
        
        if ( $insize > 5 && $inarr[1] == 353 )
        {
            $chan = $inarr[4];
            $this->names[$chan] = array();
            for ( $i = 5; $i < $insize; $i++ )
                 $this->names[$chan] []= trim($inarr[$i],"\n\r:+@");
            Logger::log("irc","!","Updated names for $chan");
            Logger::log("irc","!",print_r($this->names[$chan],true));
                
        }
        else if ( $insize > 1 && $inarr[1] == 433 )
        {
            $newnick = "{$this->nick}_";
            $this->set_nick($newnick);
            $this->apply_nick($newnick);
        }

        $from = substr(strstr($inarr[0],"!",true),1);
        $from_host = substr(strstr($inarr[0],"@"),1);
        
        if ( in_array($from,$this->blacklist) )
        {
            Logger::log("irc","!","Blacklist message from $from");
        }
        else if ( $insize > 1 )
        {
            $irc_cmd = $inarr[1];
            $chan = $insize > 2 ? $inarr[2] : null;
            
            switch($irc_cmd)
            {
                case 'JOIN':
                    $chan = trim($chan,":");
                    $this->add_name($chan,$from);
                    if ( $from == $this->nick )
						$this->add_channel($chan);
                    return new MelanoBotCommand($irc_cmd, array($from), /*$from,*/ $from, $from_host, $chan, $data, $irc_cmd);
                case 'KICK':
                    if ( $insize > 3 ) $from = $inarr[3];
                    if ( $from == $this->nick )
                        $this->join_list []= $chan;
                case 'PART':
                    if ( $from == $this->nick )
						$this->remove_channel($chan);
                    $this->remove_name($chan,$from);
                    return new MelanoBotCommand($irc_cmd, array($from), /*$from,*/ $from, $from_host, $chan, $data, $irc_cmd);
                case 'NICK':
                    $nick = trim($inarr[2],"\n\r!:");
                    if ( $from == $this->nick )
                        $this->apply_nick($nick);
                    else
                        $this->change_name($from, $nick);
                    break;
                case 'QUIT':
                    $chans = array();
                    foreach($this->names as $chan => $names)
                    {
                        if ( in_array($from,$names) )
                        {
                            $chans[] = $chan;
                            $this->remove_name($chan,$from);
                        }
                    }
                    if ( $from == $this->nick )
						$this->channels = array();
                    return new MelanoBotCommand($irc_cmd, array($from), /*$from,*/ $from, $from_host, $chans, $data, $irc_cmd);
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
                                 
                            return new MelanoBotCommand($command,$query_params,/*$query,*/$from,$from_host,$chan,$data, $irc_cmd/*, $chanhax*/);
                        }
                    }
                    return new MelanoBotCommand(null,$query_params/*,$query*/,$from,$from_host,$chan,$data, $irc_cmd);
                    
            }
        }
        
        
        return null;
        
        
    }
    
    function say($channel,$msg,$action=false)
    {
        if ( $channel != $this->nick )
        {
			if ( $action )
				$msg = irc_action($msg);
            $this->command("PRIVMSG","$channel :$msg");
		}
        else
            Logger::log("irc","!","ERROR: trying to send a message to myself",1);
    }
    
    function server_connected()
    {
		return $this->buffer->server && $this->buffer->server->connected();
    }
    
    function connection_status()
    {
		if ( !$this->server_connected() )
			return self::DISCONNECTED;
        return $this->connection_status;
    }

    
}