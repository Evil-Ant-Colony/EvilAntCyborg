<?php

class MelanoBotCommand
{
    public $cmd, $params, $from, $channel, $raw, $chanhax, $param_string;
    
    function MelanoBotCommand($cmd, $params, $from, $channel, $raw, $chanhax=false)
    {
        $this->cmd = $cmd; 
        $this->params = $params; 
        $this->from = $from; 
        $this->channel = $channel;
        $this->raw = $raw; 
        $this->chanhax = $chanhax;
        $this->param_string = implode(' ',$params);
    }
    
    
    static function create_from_raw($data, $inarr, $private)
    {
        $from = substr(strstr($inarr[0],"!",true),1);
        $min_param = $private ? 3 : 4;
        $insize = count($inarr);
        $command = $insize > $min_param ? strtolower(trim($inarr[$min_param]," \t\n\r\0\x0B!:")) : "";
        $params = array();
        $max_param = $insize;
        $channel = $private ? $from : $inarr[2];
        $chanhax = false;
        if ( $inarr[$insize-2] == 'chanhax' )
        {
            $channel = $inarr[$insize-1];
            $max_param = $insize-2;
            $chanhax = true;
        }
                    
        for ( $i = $min_param+1; $i < $max_param; $i++ )
        {
            $params []= $inarr[$i];
        }
                
                    
        return new MelanoBotCommand($command, $params, $from, $channel, $data, $chanhax);
    }
}

class MelanoBot
{
    private $socket;
    public $server, $port, $real_name, $nick, $password;
    public $channels, $blacklist;
    private $v_connected = 0;
    
    function MelanoBot($server, $port, $real_name, $nick, $password, 
                 $channels, $blacklist=array())
    {
        $this->socket = fsockopen($server,$port);
        $this->server = $server;
        $this->port = $port;
        $this->real_name = $real_name;
        $this->nick = $nick;
        $this->password = $password;
        $this->blacklist = $blacklist;
        $this->channels = $channels;
    }
    
    function login()
    {
        $this->login_ext($this->real_name,$this->nick);
        $this->v_connected = 1;
    }
    
    function login_ext($real_name, $nick)
    {
        $this->command('USER',"$nick localhost $nick :$real_name");
        $this->command('NICK', $nick);
    }
    
    function auth()
    {
        if ( !is_null($this->password) )
            $this->command('AUTH', $this->nick." ".$this->password);
    }
    
    function command($command, $data)
    {
        if ( $this->socket !== false )
        {
            fputs($this->socket,"$command $data\n\r");
            echo "(send) $command $data\n";
        }
    }
    
    function join($channels)
    {
        if ( !is_array($channels) )
            $channels = array($channels);
        
        $this->channels = array_unique(array_merge($this->channels,$channels));
        foreach($channels as $channel)
            $this->command('JOIN',$channel);
    }
    
    function quit($message="bye!")
    {
        $this->command('QUIT',$message);
        $this->v_connected = 0;
    }
    
    function loop_step()
    {
        if ( $this->socket === false || feof($this->socket) )
        {
            $this->quit();
            echo "Network Quit\n";
            return null;
        }
        
        $data = fgets($this->socket,512);
        
        echo "=======data=========\n$data========end========\n";
        
        $inarr = explode(' ',trim($data));
        if ( $inarr[0] == 'PING' )
        {
            $this->command('PONG',$inarr[1]);
            $this->v_connected++;
        }
        
        if ( strpos($data,"MODE ".$this->nick." +i") )
        {
            $this->v_connected++;
        }
        
        if ( $this->v_connected == 3 )
        {
            $this->v_connected++;
            $this->auth();
            $this->join($this->channels);
        }
        
        $from = substr(strstr($inarr[0],"!",true),1);
        $insize = count($inarr);
        if ( in_array($from,$this->blacklist) )
        {
            echo "Blacklist message from $from\n";
        }
        else if ( $insize > 2 && $inarr[1] == "JOIN" )
        {
            
            return new MelanoBotCommand("greet", array($from), $from, $inarr[2], $data);

        }
        else if ( $this->fully_connected() && $insize > 3 )
        {
            $private = false;
            if ( trim($inarr[2]) == $this->nick )
                $private = true;
                
            if ( $from == $this->nick )
            {
                echo "ERROR: got a message from myself\n";
            }
            else if ( $from != "" && ( $private || trim($inarr[3]) == ":".$this->nick.":" ) )
            {
                return MelanoBotCommand::create_from_raw($data, $inarr, $private);
            }
            
        }
        
        
        return null;
        
        
    }
    
    function say($channel,$msg)
    {
        if ( $channel != $this->nick )
            $this->command("PRIVMSG","$channel :$msg");
        else
            echo "ERROR: trying to send a message to myself\n";
    }
    
    function connected()
    {
        return $this->v_connected;
    }
    
    function fully_connected()
    {
        return $this->v_connected >= 3;
    }
    
}