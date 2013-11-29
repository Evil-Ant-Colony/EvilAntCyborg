<?php

class MelanoBotCommand
{
    public $cmd, $params, $from, $host, $channel, $raw, $chanhax, $param_string, $irc_cmd;
    
    function MelanoBotCommand($cmd, $params, $query, $from, $host, $channel, $raw, $irc_cmd, $chanhax=false)
    {
        $this->cmd = $cmd; 
        $this->params = $params; 
        $this->from = $from; 
        $this->host = $host; 
        $this->channel = $channel;
        $this->raw = $raw; 
        $this->chanhax = $chanhax;
        $this->param_string = $query;
        $this->irc_cmd = $irc_cmd;
    }
    
    /**
     * \brief Check that $this->from / $this->host are found in $user (nick=>host|null)
     */
    function check($users)
    {
        foreach ( $users as $nick => $host )
        {
            if ( $host == null )
            {
                if ( $nick == $this->from )
                    return true;
            }
            else if ( $this->host == $host ) 
                return true;
        }
        
        return false;
    }
}

class MelanoBot
{
    private $socket;
    public $server, $port, $real_name, $nick, $auth_nick, $password;
    public $blacklist, $listen_to;
    public $mode = null;
    private $v_connected = 0;
    private $names = array();
    private $join_list = array();
    public $strip_colors = false;
    
    
    function MelanoBot($server, $port, $nick, $password, 
                 $channels, $blacklist=array())
    {
        $this->socket = fsockopen($server,$port);
        $this->server = $server;
        $this->port = $port;
        $this->real_name = $nick;
        $this->auth_nick = $nick;
        $this->nick = $nick;
        $this->password = $password;
        $this->blacklist = $blacklist;
        $this->join_list = $channels;
        $this->listen_to = "$nick:";
    }
    
    /// send a request to change the nick
    function set_nick($nick)
    {
        $this->command('NICK',$nick);
        echo "Nick chang request: $nick\n";
    }
    /// Apply the new nick (after the server has accepted it)
    private function apply_nick($nick)
    {
        $this->change_name($this->nick, $nick);
        $this->nick = $nick;
        $this->listen_to = "$nick:";
        echo "Nick changed to $nick\n";
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
        echo "Updated names for $chan (+$name)\n";
        print_r($this->names[$chan]);
    }
    
    private function remove_name($chan,$name)
    {
        if (($key = array_search($name, $this->names[$chan])) !== false) 
        {
            echo "Updated names for $chan (-$name)\n";
            array_splice($this->names[$chan],$key,1);
            print_r($this->names[$chan]);
        }
        else
        {
            echo "Not removing $name from $chan\n";
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
        echo "Updated names ($name_old->$name_new)\n";
        print_r($this->names);
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
        if ( !is_null($this->password) && !is_null($this->auth_nick) )
        {
            $this->command('AUTH', $this->auth_nick." ".$this->password);
            if ( strlen($this->modes) > 0 )
                $this->command('MODE', $this->nick." +".$this->modes);
        }
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
        
        if ( $this->strip_colors )
            $data = preg_replace("{\x03([0-9][0-9]?)?(,[0-9][0-9]?)?}","",$data);
        
        $inarr = explode(' ',trim($data));
        $insize = count($inarr);
        if ( $inarr[0] == 'PING' )
        {
            $this->command('PONG',$inarr[1]);
            $this->v_connected++;
        }
        
        if ( $insize > 1 && $inarr[1] == 221  )
        {
            $this->v_connected++;
        }
        
        if ( $this->v_connected == 3 )
        {
            $this->v_connected++;
            $this->auth();
        }
        
        if ( $this->v_connected > 3 && !empty($this->join_list) )
        {
            echo "Join\n";
            $this->join($this->join_list);
        }
        
        if ( $insize > 5 && $inarr[1] == 353 )
        {
            $chan = $inarr[4];
            $this->names[$chan] = array();
            for ( $i = 5; $i < $insize; $i++ )
                 $this->names[$chan] []= trim($inarr[$i],"\n\r:+@");
            echo "Updated names for $chan\n";
            print_r($this->names[$chan]);
                
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
            echo "Blacklist message from $from\n";
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
                    return new MelanoBotCommand("greet", array($from), $from, $from, $from_host, $chan, $data, $irc_cmd);
                case 'KICK':
                    if ( $insize > 3 ) $from = $inarr[3];
                    if ( $from == $this->nick )
                        $this->join_list []= $chan;
                case 'PART':
                    $this->remove_name($chan,$from);
                    return new MelanoBotCommand("bye", array($from), $from, $from, $from_host, $chan, $data, $irc_cmd);
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
                    return new MelanoBotCommand("bye", array($from), $from, $from, $from_host, $chans, $data, $irc_cmd);
                case 'PRIVMSG':
                    $query = trim(substr($data,strpos($data,':',1)+1));
                    $query_params = explode(' ',$query);
                    if ( $insize > 3 )
                    {
                        $inarr[3] = ltrim($inarr[3],":");
                        $inarr[$insize-1] = rtrim($inarr[$insize-1]);
                        
                        if ( $from == $this->nick )
                        {
                            echo "Got a message from myself\n";
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
                            
                            $chanhax = false;
                            $n_params=count($query_params);
                            if ( $n_params > 2 && $query_params[$n_params-2] == 'chanhax' )
                            {
                                $chan = array_pop($query_params);
                                $chanhax = true;
                                array_pop($query_params); // remove chanhax
                            }
                            $query = implode(' ',$query_params);
                                 
                            return new MelanoBotCommand($command,$query_params,$query,$from,$from_host,$chan,$data, $irc_cmd, $chanhax);
                        }
                    }
                    return new MelanoBotCommand(null,$query_params,$query,$from,$from_host,$chan,$data, $irc_cmd);
                    
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