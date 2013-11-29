<?php

require("melanobot.php");
require("cuphelp.php");
require("cupmanager.php");
require("setup-cup.php");

$map_pick = null;
$map_pick_stage = 0;

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

function check_cup($cmd,$bot)
{   
    global $cups, $cup;
    if ( $cup != null )
        return true;
    if ( empty($cups) )
    {
        $bot->say($cmd->channel,"No tournament is currently scheduled");
        return false;
    }
    else
        $cup = $cups[0];
    return true;
}

function map_pick_show_turn($cmd,$bot)
{
    global $map_pick;
    $dp = $map_pick->is_picking() ? "\x0303PICK\x03" : "\x0304DROP\x03";
    $bot->say($cmd->channel,$map_pick->current_player().", your turn");
    $bot->say($cmd->channel,"$dp ".implode(', ',$map_pick->maps));
}

function commands_map_pick_setup($cmd,$bot)
{
    global $map_pick, $map_pick_stage;
    
    if ( !check_admin($cmd) )
    {
        if ( $map_pick->is_player($cmd->from) )
            $bot->say($cmd->channel,"{$cmd->from}: Wait until an admin starts the map picking");
        return false;
    }
    
    switch($cmd->cmd)
    {
        case 'start':
            $bot->say($cmd->channel,"Starting map picking ");
            $bot->say($cmd->channel,$map_pick->player[0]." vs ".$map_pick->player[1]);
            $bot->say($cmd->channel,$map_pick->pick_drops());
            map_pick_show_turn($cmd,$bot);
            $map_pick_stage = 1;
            return true;
        case 'nick':
            if ( count($cmd->params) == 2 && $map_pick->is_player($cmd->params[0]) )
            {
                foreach ( $map_pick->player as &$p )
                    if ( $cmd->params[0] == $p )
                    {
                        $p = $cmd->params[1];
                        break;
                    }
               $bot->say($cmd->channel,"Listen to {$cmd->params[1]} as map picker for {$cmd->params[0]}");
            }
            else
            {
                $bot->say($cmd->channel,"Currently listening to ".
                                            implode(' and ',$map_pick->player));
            }
            return true;
        case 'pick':
            if ( count($cmd->params) >= 1 )
            {
                $map_pick->pick_num = (int)$cmd->params[0];
                $bot->say($cmd->channel,"Map picking: ".$map_pick->pick_drops());
            }
            else
            {
                $bot->say($cmd->channel,"How many picks?");
            }
            return true;
        default:
            return false;
    }
}

function commands_map_pick($cmd,$bot)
{
    global $map_pick, $map_pick_stage;
    
    if ( $cmd->cmd == 'stop' && check_admin($cmd) )
    {
        $bot->say($cmd->channel,"Map picking stopped");
        $totmaps = array_merge($map_pick->picks,$map_pick->maps);
        $bot->say($cmd->channel,"Remaining maps: ".implode(', ',$totmaps));
        $map_pick = null;
        $map_pick_stage = 0;
        return true;
    }
    
    if ( $map_pick == null )
        return false;
        
    
    if ( !check_admin($cmd) && !$map_pick->is_player($cmd->from) )
    {
        return false;
    }
    else if ( $cmd->cmd == 'turn' )
    {
        map_pick_show_turn($cmd,$bot);
    }
        
    if ( $map_pick_stage == 0 )
        return commands_map_pick_setup($cmd,$bot);
        
   if ( $cmd->from == $map_pick->current_player() )
   {
        $map = $cmd->cmd == null && !empty($cmd->params) ? $cmd->params[0] : $cmd->cmd;
        if ( !$map_pick->has_map($map) )
        {
            $bot->say($cmd->channel,"Map $map is not in the list");
        }
        else
        {
            $map_pick->choose($map);
            if ( count($map_pick->maps) == 1 )
            {
                $map_pick->picks []= $map_pick->maps[0];
                $bot->say($cmd->channel,"Map picking ended");
                $bot->say($cmd->channel,"Result: ".implode(', ',$map_pick->picks));
                $map_pick = null;
                $map_pick_stage = 0;
                return true;
            }
            $map_pick->next_round();
        }
        
        map_pick_show_turn($cmd,$bot);
        return true;
   }
   else if ( $map_pick->is_player($cmd->from) )
   {
        $bot->say($cmd->channel,"Wait for your turn {$cmd->from}");
        return true;
   }
   
   return false;
    
}

function commands_cup($cmd,$bot)
{
    global $cups, $cup, $cup_manager, $map_pick, $map_pick_stage;
    
    if ( $map_pick && commands_map_pick($cmd,$bot) )
        return true; 
    
    switch($cmd->cmd)
    {
        case 'next':
            if ( check_cup($cmd,$bot) )
            {
                $num = isset($cmd->params[0]) ? (int)$cmd->params[0] : 1;
                if ( $num > 5 && !check_admin($cmd) )
                    $num = 5;
                $bot->say($cmd->channel,"Fetching...");
                $matches = $cup_manager->open_matches($cup->id);
                $num = min($num,count($matches));
                if ( count($matches) == 0 )
                {
                    $bot->say($cmd->channel,"No matches are currently available");
                }
                else
                {
                    for ( $i = 0; $i < $num; $i++ )
                    {
                        $match = $matches[$i];
                        if ( $match == null )
                            break;
                        $bot->say($cmd->channel,
                            $match->id.": ".$match->team1()." vs ".$match->team2());
                        sleep(1+$i/10);
                    }
                }
            }
            return true;
            
        case 'score':
            if ( check_cup($cmd,$bot) && count($cmd->params) >= 1 )
            {
                $match = $cup_manager->match($cup->id,$cmd->params[0]);
                if ( $match == null )
                {
                    $bot->say($cmd->channel,"Match ".$cmd->params[0]." not found");
                    break;
                }
                
                if ( count($cmd->params) == 3 && check_admin($cmd) ) // matchID score1 score2
                {
                    $match->team1->add_score($cmd->params[1]);
                    $match->team2->add_score($cmd->params[2]);
                    $bot->say($cmd->channel,"Updated match ".$cmd->params[0].":");
                    $cup_manager->update_match($cup,$match);
                }
                else
                    $bot->say($cmd->channel,"Match {$match->id}:");
                $t1 = $match->team1();
                $t2 = $match->team2();
                $len=max(strlen($t1),strlen($t2));
                $bot->say($cmd->channel,str_pad($t1,$len).": ".$match->score1());
                $bot->say($cmd->channel,str_pad($t2,$len).": ".$match->score2()); 
            }
            return true;
            
        case 'end':
            if ( check_cup($cmd,$bot) && count($cmd->params) == 1 && check_admin($cmd) )
            {
                $match = $cup_manager->match($cup->id,$cmd->params[0]);
                if ( $match == null )
                {
                    $bot->say($cmd->channel,"Match ".$cmd->params[0]." not found");
                    break;
                }
                
                $win = $match->winner();
                if ( $win == null )
                {
                    $bot->say($cmd->channel,"Cannot end ".$cmd->params[0]." (no winner)");
                    break;
                }
                
                $cup_manager->end_match($cup,$match);
                $bot->say($cmd->channel,"{$win->name} won match {$match->id} (".$match->team1()." vs ".$match->team2().")");
            }
            return true;
        
        case 'results':
            if ( check_cup($cmd,$bot) )
                $bot->say($cmd->channel,$cup->result_url());
            return true;
            
        case 'cup':
            if ( !empty($cmd->params) && check_admin($cmd) )
            {
                $next = trim($cmd->param_string);
                $bool = false;
                foreach($cups as $c)
                {
                    if ( $c->id == $next || $c->name == $next )
                    {
                        $cup = $c;
                        $bool = true;
                        break;
                    }
                }
                if ( $bool )
                {
                    $bot->say($cmd->channel,"Cup switched to: {$cup->name} - {$cup->id}");
                    break;
                }
                else
                    $bot->say($cmd->channel,"Cup \"$next\" not found");
            }
            
            if ( check_cup($cmd,$bot) )
                $bot->say($cmd->channel,"Current cup: {$cup->name} - {$cup->id}");
                
            return true;
            
        case 'cups':
            if ( check_admin($cmd) )
            {
                $cups = $cup_manager->tournaments();
                
                if ( empty($cups) )
                    $bot->say($cmd->channel,"No cups available");
                else
                {
                    $text = "Available cups: ";
                    foreach ( $cups as $c )
                    {
                        if ( $cup != null && $c->id == $cup->id )
                            $text .= "*";
                        $text .= "{$c->name} ({$c->id}), ";
                    }
                    $bot->say($cmd->channel,$text);
                }
            }
            return true;
            
        case 'pick':
            if ( check_cup($cmd,$bot) && check_admin($cmd) )
            {
                if ( empty($cmd->params) )
                {
                    $bot->say($cmd->channel,"Match ID?");
                    break;
                }
                if ( empty($cup->maps) )
                {
                    $bot->say($cmd->channel,"No maps to pick...");
                    break;
                }
                
                $match = $cup_manager->match($cup->id,$cmd->params[0]);
                if ( $match == null )
                {
                    $bot->say($cmd->channel,"Match ".$cmd->params[0]." not found");
                    break;
                }
                
                $map_pick = new MapPicker($match->team1(),$match->team2(),$cup->maps);
                $map_pick_stage = 0;
                
                $bot->say($cmd->channel, "Setting up picking for {$match->id}: ".
                                        $map_pick->player[0]." vs ".$map_pick->player[1]);
                
            }
            return true;
            
        case 'maps':
            if ( check_cup($cmd,$bot) )
            {
                if ( count($cmd->params) == 1 && $cmd->params[0] == 'count' )
                {
                    $bot->say($cmd->channel,count($cup->maps)." maps");
                }
                else if ( count($cmd->params) > 1 && check_admin($cmd) )
                {
                    $direction = array_shift($cmd->params);
                    if ( $direction == '+' || $direction == 'add' )
                    {
                        foreach($cmd->params as $map)
                        {
                            $cup->add_map($map);
                            echo "Maps + $map\n";
                        }
                        $cup_manager->update_cup($cup);
                        
                    }
                    else if ( $direction == '-' || $direction == 'remove' )
                    {
                    
                        foreach($cmd->params as $map)
                            $cup->remove_map($map);
                        $cup_manager->update_cup($cup);
                    }
                    else
                        $bot->say($cmd->channel,"Ignoring unknown argument");
                }
                if ( count($cup->maps) > 0 )
                    $bot->say($cmd->channel,implode(', ',$cup->maps));
                else
                    $bot->say($cmd->channel,"No maps");
            }
            return true;
            
        case 'description':    
            if ( check_cup($cmd,$bot) )
            {
                if ( check_admin($cmd) && !empty($cmd->params) )
                {
                    $cup->description = $cmd->param_string;
                    $cup_manager->update_cup($cup);
                }
                $bot->say($cmd->channel,"Cup {$cup->name} ({$cup->id}): {$cup->description}");
            }
            return true;
            
        case 'start':
            if ( check_cup($cmd,$bot) && check_admin($cmd) )
            {
                $cup->start();
                $cup->start_time = time();
                $bot->say($cmd->channel,"Cup started");
                $cup_manager->update_cup($cup);
            }
            return true;
            
        case 'time':
            if ( check_cup($cmd,$bot) )
            {
                if ( check_admin($cmd) && !empty($cmd->params) )
                {
                    $time = strtotime($cmd->param_string);
                    if ( $time != null )
                    {
                        $cup->start_time = $time;
                        $cup_manager->update_cup($cup);
                    }
                    else
                        $bot->say($cmd->channel,"Invalid time format");
                }
                
                if ( $cup->start_time == null )
                    $bot->say($cmd->channel,"Current cup is currently not scheduled");
                else if ( $cup->start_time <= time() )
                    $bot->say($cmd->channel,"Cup already started");
                else
                {
                    $delta = $cup->start_time - time();
                    $d_day = (int) ($delta / (60*60*24));
                    $d_hour = (int) ($delta % (60*60*24) / (60*60));
                    $d_min = round($delta % (60*60) / 60);
                    $d_string = "";
                    if ( $d_day > 0 )
                        $d_string .= "$d_day days, ";
                    if ( $d_hour > 0 || $d_day > 0 )
                        $d_string .= "$d_hour hours, ";
                    $d_string .= "$d_min minutes, ";
                    $bot->say($cmd->channel,"Cup will start in $d_string");
                }
            }
            return true;
            
        default:
            return false;    
    }
    return true;
}

function commands_owner($cmd,$bot)
{
    if ( ! check_owner($cmd) )
        return false;
        
    switch($cmd->cmd)
    {
        case 'quit': 
            $bot->quit();
            return true;
        case 'restart':
            $bot->quit("See y'all in a sec");
            touch(".restartbot");
            return true;
        default:
            return false;
    }
}

function commands_admin($cmd,$bot)
{
    if ( ! check_admin($cmd) )
        return false;
        
    global $whitelist, $soft_blacklist;
    
    switch ($cmd->cmd)
    {
        case 'whitelist':
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
            return true;
        case 'nowhitelist':
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
            return true;
        case 'blacklist':
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
            return true;
        case 'noblacklist':
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
            return true;
        default:
            return false;
    }
}


function commands_always($cmd,$bot)
{
    global $help;
    
    switch($cmd->cmd)
    {
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
            return true;
    }
    return false;
}

$bot->login();


while(true)
{
    $cmd = $bot->loop_step();
    if ( !$bot->connected() )
        break;
    
    if ( $cmd != null )
    {
        print_r($cmd);
        if ( filter($cmd,$bot)  )
        {
                commands_owner($cmd,$bot) 
            or  commands_admin($cmd,$bot) 
            or  commands_always($cmd,$bot)
            or  commands_cup($cmd,$bot) 
            ;
        }
    }
    //sleep(1);
}
