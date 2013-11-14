#!/usr/bin/php
<?php

require("melanobot.php");
require("setup-bot.php");


function is_admin($user)
{
    global $daddy, $whitelist;
    return is_owner($user) || in_array($user, $whitelist);
}

function is_owner($user)
{
    global $daddy;
    return $user == $daddy;
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
        if ( $cmd->chanhax && !is_admin($cmd->from)  )
        {
            $bot->say($cmd->from,"Sorry ".$cmd->from." but only evil ants can do this hax" );
        }
        else
        {
            switch($cmd->cmd)
            {
                case 'shut': 
                case 'quit': 
                    if ( is_owner($cmd->from) )
                        $bot->quit(); 
                    else
                        $bot->say($cmd->channel,"Shut up ".$cmd->from);
                    break;
                case 'join': 
                    if ( is_owner($cmd->from) && isset($cmd->params[0]))
                        $bot->join($cmd->params[0]);
                    else
                        $bot->say($cmd->channel,"No!");
                    break;
                case 'nick':
                    if ( is_owner($cmd->from) && isset($cmd->params[0]))
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
                    $from = " {$cmd->from}";
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
                    foreach($cmd->channel as $chan )
                        $bot->say($chan,"We'll miss you {$cmd->from}!");
                    break;
                case 'cmd':
                    if ( is_owner($cmd->from) )
                    {
                        $command = array_shift($cmd->params);
                        $bot->command($command, implode(' ',$cmd->params)); 
                    }
                    else
                        $bot->say($cmd->channel,"Shut up ".$cmd->from);
                    break;
                    
                case 'whitelist':
                    if ( is_admin($cmd->from) )
                    {
                        $who = isset($cmd->params[0]) ? trim($cmd->params[0]) : "";
                        if ( $who == "" )
                            $bot->say($cmd->channel,"Who?");
                        else if ( $who == $bot->nick )
                            $bot->say($cmd->channel,"Who, me?");
                        else
                        {
                            $whitelist []= $who;
                            $whitelist = array_unique($whitelist);
                            $bot->say($cmd->channel,"OK, $who is in whitelist");
                        }
                    }
                    else
                    {
                        $bot->say($cmd->channel,"No!");
                    }
                    break;
                case 'nowhitelist':
                    if ( is_admin($cmd->from) )
                    {
                        $who = isset($cmd->params[0]) ? trim($cmd->params[0]) : "";
                        if ( $who == "" )
                            $bot->say($cmd->channel,"Who?");
                        else if ( is_owner($who) )
                            $bot->say($cmd->channel,"But $who is my daddy!");
                        else if (($key = array_search($who,$whitelist)) !== false) 
                        {
                            array_splice($whitelist,$key,1);
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
                case 'do':
                    if ( is_admin($cmd->from) )
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
                        $bot->say($cmd->channel,"<{$msg[from]}> {$msg[msg]}");
                        $bot->say($cmd->channel,"You have other ".count($messages[$cmd->from]["queue"])." messages");
                    }
                    else
                        $bot->say($cmd->channel, "You don't have any message");
                    break;
                case null:
                    if ( strpos($cmd->raw,"http://www.youtube.com/watch?v=") !== false )
                        $bot->say($cmd->channel,"Ha Ha! Nice vid {$cmd->from}!");
                    break;
                default: 
                    if ( !extra_commands($cmd,$bot) )
                        $bot->say($cmd->channel,"What?");
            }
        }
    }
    //sleep(1);
}


