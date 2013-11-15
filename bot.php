#!/usr/bin/php
<?php

require("melanobot.php");
require("help.php");
require("setup-bot.php");


/*function is_admin($user)
{
    global $whitelist;
    return is_owner($user) || in_array($user, $whitelist);
}*/

function is_owner($user)
{
    global $owners;
    return isset($owners[$user]);
}

function check_owner($cmd)
{
    global $owners;
    return $cmd->check($owners);
}


function check_admin($cmd)
{
    global $whitelist, $owners;
    return $cmd->check($owners) || $cmd->check($whitelist);
}

function helpcheck($help_cmd,$cmd)
{
    global $help;
    if ( !is_array($help_cmd) )
    {
        if ( !isset($help[$help_cmd]) )
            return false;
        $help_cmd = $help[$help_cmd];
    }
    
    switch ( $help_cmd['auth'] )
    {
        case ANYONE: return true;
        case ADMIN: return check_admin($cmd);
        case OWNER: return check_owner($cmd);
        default: return false;
    }
}

$bot->login();

$messages = array();


while(true)
{
    $cmd = $bot->loop_step();
    if ( !$bot->connected() )
        break;
    
    if ( $cmd != null )
    {
        print_r($cmd);
        if ( filter($cmd,$bot) )
        {
            switch($cmd->cmd)
            {
                case 'ragequit': 
                case 'shut': 
                case 'quit': 
                    if ( check_owner($cmd) )
                        $bot->quit(); 
                    else
                        $bot->say($cmd->channel,"Shut up ".$cmd->from);
                    break;
                case 'restart':
                    if ( check_owner($cmd) )
                    {
                        $bot->quit("See y'all in a sec");
                        touch(".restartbot");
                    }
                    else
                        $bot->say($cmd->channel,"Shut up ".$cmd->from);
                    break;
                case 'join': 
                    if ( check_owner($cmd) && isset($cmd->params[0]))
                        $bot->join($cmd->params[0]);
                    else
                        $bot->say($cmd->channel,"No!");
                    break;
                case 'part': 
                    if ( check_owner($cmd) )
                    {
                        $chan = isset($cmd->params[0]) ? $cmd->params[0] : $cmd->channel;
                        $bot->command('PART',$chan);
                    }
                    else
                        $bot->say($cmd->channel,"No!");
                    break;
                case 'nick':
                    if ( check_owner($cmd) && isset($cmd->params[0]))
                        $bot->set_nick($cmd->params[0]);
                    else
                        $bot->say($cmd->channel,"No!");
                    break;
                    
                case 'say':
                    $bot->say($cmd->channel,$cmd->param_string);
                    break;
                case 'slap':
                    $who = $cmd->from;
                    if ( strlen(trim($cmd->param_string)) > 0 )
                        $who = $cmd->param_string;
                    $bot->say($cmd->channel,"\x01ACTION slaps $who\x01");
                    break;
                    
                case 'greet':
                case 'hello': 
                    $from=" {$cmd->from}";
                    if ( $cmd->from == $bot->nick )
                        $from = "";
                    $bot->say($cmd->channel,"Hello$from!!!");
                    
                    if ( isset($messages[$cmd->from]) && !$messages[$cmd->from]["notified"] )
                    {
                        $messages[$cmd->from]["notified"] = true;
                        $bot->say($cmd->from,"You have ".count($messages[$cmd->from]["queue"])." messages");
                    }
                    break;
                case 'bye':
                    if ( !is_array($cmd->channel) )
                        $cmd->channel = array($cmd->channel);
                    $message = "We'll miss you {$cmd->from}!";
                    if ( $cmd->irc_cmd == 'KICK' )
                    {
                        if ( $cmd->from == $bot->nick )
                            $message = "Why??";
                        else
                            $message = "We won't miss {$cmd->from}!";
                    }
                    foreach($cmd->channel as $chan )
                        $bot->say($chan,$message);
                    break;
                case 'cmd':
                    if ( check_owner($cmd) )
                    {
                        $command = array_shift($cmd->params);
                        $bot->command($command, implode(' ',$cmd->params)); 
                    }
                    else
                        $bot->say($cmd->channel,"Shut up ".$cmd->from);
                    break;
                    
                case 'whitelist':
                    if ( check_admin($cmd) )
                    {
                        $who = isset($cmd->params[0]) ? trim($cmd->params[0]) : "";
                        if ( $who == "" )
                            $bot->say($cmd->channel,"Who?");
                        else if ( $who == $bot->nick )
                            $bot->say($cmd->channel,"Who, me?");
                        else
                        {
                            $host = isset($cmd->params[1]) ? $cmd->params[1] : null;
                            $whitelist [$who]= $host;
                            $bot->say($cmd->channel,"OK, $who is in whitelist");
                        }
                    }
                    else
                    {
                        $bot->say($cmd->channel,"No!");
                    }
                    break;
                case 'nowhitelist':
                    if ( check_admin($cmd) )
                    {
                        $who = isset($cmd->params[0]) ? trim($cmd->params[0]) : "";
                        if ( $who == "" )
                            $bot->say($cmd->channel,"Who?");
                        else if ( is_owner($who) )
                            $bot->say($cmd->channel,"But $who is my daddy!");
                        else if ( array_key_exists($who,$whitelist) ) 
                        {
                            unset($whitelist[$who]);
                            $bot->say($cmd->channel,"OK, $who is no longer in whitelist");
                        }
                        else
                        {
                            $bot->say($cmd->channel,"But...");
                        }
                    }
                    else
                    {
                        $bot->say($cmd->channel,"No!");
                    }
                    break;
                case 'blacklist':
                    if ( check_admin($cmd) )
                    {
                        $who = isset($cmd->params[0]) ? trim($cmd->params[0]) : "";
                        if ( $who == "" )
                            $bot->say($cmd->channel,"Who?");
                        else if ( $who == $bot->nick )
                            $bot->say($cmd->channel,"Who, me?");
                        else if ( is_owner($who) ) 
                            $bot->say($cmd->channel,"But $who is my daddy!");
                        else
                        {
                            $soft_blacklist []= $who;
                            $soft_blacklist = array_unique($soft_blacklist);
                            $bot->say($cmd->channel,"OK, $who is in blacklist");
                        }
                    }
                    else
                    {
                        $bot->say($cmd->channel,"No!");
                    }
                    break;
                case 'noblacklist':
                    if ( check_admin($cmd) )
                    {
                        $who = isset($cmd->params[0]) ? trim($cmd->params[0]) : "";
                        if ( $who == "" )
                            $bot->say($cmd->channel,"Who?");
                        else if (($key = array_search($who,$soft_blacklist)) !== false)
                        {
                            array_splice($soft_blacklist,$key,1);
                            $bot->say($cmd->channel,"OK, $who is no longer in blacklist");
                        }
                        else
                        {
                            $bot->say($cmd->channel,"But...");
                        }
                    }
                    else
                    {
                        $bot->say($cmd->channel,"No!");
                    }
                    break;
                case 'please':
                    if ( check_admin($cmd) )
                    {
                        $bot->say($cmd->channel,"\x01ACTION {$cmd->param_string}\x01");
                    }
                    else
                    {
                        $bot->say($cmd->channel,"Won't do!");
                    }
                    break;
                case 'tell':
                    $who = isset($cmd->params[0]) ? trim($cmd->params[0]) : "";
                    if ( $who == "" )
                        $bot->say($cmd->channel,"Who?");
                    else if ( $who == $bot->nick )
                        $bot->say($cmd->channel,"Who, me?");
                    else if ( $who == $cmd->from )
                        $bot->say($cmd->channel, "Poor $who, talking to themselves...");
                    else
                    {
                        $found = $bot->find_name($who,$cmd->channel);
                        array_shift($cmd->params);
                        $text = implode(' ',$cmd->params);
                        if ( $found == 2 )
                            $bot->say($cmd->channel, "$who is right here, you can talk to them right now...");
                        else if ( $found == 1 )
                        {
                            $bot->say($who,"<{$cmd->from}> $text");
                            $bot->say($cmd->channel, "Done!");
                        }
                        else
                        {
                            if ( !isset($messages[$who]) )
                                $messages[$who] = array("notified"=>false,"queue"=>array());

                            
                            $messages[$who]["notified"] = false;
                            $messages[$who]["queue"][]= array("from"=>$cmd->from,"msg"=>$text);
                            echo "Stored a message from {$cmd->from} to $who\n";
                            $bot->say($cmd->channel, "Will do!");
                            
                                
                        }
                    }
                    break;
                case 'message':
                    if ( isset($messages[$cmd->from]) && count($messages[$cmd->from]["queue"]) > 0 )
                    {
                        $messages[$cmd->from]["notified"] = true;
                        $msg = array_shift($messages[$cmd->from]["queue"]);
                        $bot->say($cmd->channel,"<{$msg['from']}> {$msg['msg']}");
                        $bot->say($cmd->channel,"You have other ".count($messages[$cmd->from]["queue"])." messages");
                    }
                    else
                        $bot->say($cmd->channel, "You don't have any message");
                    break;
                case 'help':
                    if ( count($cmd->params) > 0 )
                    {
                        $i = 0;
                        foreach ( $cmd->params as $hc )
                        {
                            $hc = strtolower($hc);
                            if ( helpcheck($hc,$cmd) )
                            {
                                $help_item=$help[$hc];
                                $bot->say($cmd->channel,"\x0304$hc\x03: \x0314{$help_item['synopsis']}\x03");
                                $bot->say($cmd->channel,"\x0302{$help_item['desc']}\x03");
                            }
                            else
                                $bot->say($cmd->channel,"You can't do $hc");
                            $i++;
                            sleep(1+$i/5);
                        }
                        if ( count($cmd->params) > 1 )
                            $bot->say($cmd->channel,"(End of help list)");
                    }
                    else
                    {
                        $list = array();
                        foreach ( array_keys($help) as $hc )
                        {
                            if ( helpcheck($hc,$cmd) )
                                $list[]=$hc;
                        }
                        $bot->say($cmd->channel, implode(' ',$list));
                    }
                    
                    break;
                case null:
                    extra_raw_commands($cmd,$bot);
                    break;
                default: 
                    if ( !extra_commands($cmd,$bot) )
                        $bot->say($cmd->channel,"What?");
            }
        }
    }
    //sleep(1);
}


