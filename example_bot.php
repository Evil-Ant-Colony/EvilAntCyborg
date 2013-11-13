#!/usr/bin/php
<?php

require("melanobot.php");

$bot = new MelanoBot('euroserv.fr.quakenet.org',6667,'ExampleBot','ExampleBot',
                       null,array('#ExampleBot'),array("Q"));
 

$bot->login();
$daddy = 'BotAdminNick';


while(true)
{
    $cmd = $bot->loop_step();
    if ( !$bot->connected() )
        break;
    
    if ( $cmd != null )
    {
        print_r($cmd);
        if ( $cmd->chanhax && $cmd->from != $daddy )
        {
            $bot->say($cmd->from,"Sorry ".$cmd->from." but only $daddy can do this hax" );
        }
        else
        {
            switch($cmd->cmd)
            {
                case 'say':
                    $bot->say($cmd->channel,$cmd->param_string);
                    break;
                case 'slap':
                    $who = $cmd->from;
                    if ( strlen(trim($cmd->param_string)) > 0 )
                        $who = $cmd->param_string;
                    $bot->say($cmd->channel,"\x01ACTION slaps $who\x01");
                    break;
                case 'quit': 
                    if ( $cmd->from == $daddy )
                        $bot->quit(); 
                    else
                        $bot->say($cmd->channel,"Shut up ".$cmd->from);
                    break;
                case 'join': 
                    if ( $cmd->from == $daddy && isset($cmd->params[0]))
                        $bot->join($cmd->params[0]);
                    else
                        $bot->say($cmd->channel,"No!");
                    break;
                    
                case 'greet':
                case 'hello': 
                    $from = $cmd->from;
                    if ( $from == $bot->nick )
                        $from = "";
                    $bot->say($cmd->channel,"Hello $from!!!");
                    break;
                case 'bye':
                    if ( !is_array($cmd->channel) )
                        $cmd->channel = array($cmd->channel);
                    foreach($cmd->channel as $chan )
                        $bot->say($chan,"We'll miss you {$cmd->from}!");
                    break;
                default: 
                    $bot->say($cmd->channel,"What?");
            }
        }
    }
}


