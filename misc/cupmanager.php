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

class MapPicker
{
    public $player, $maps, $turn, $pick_num = 1, $picks = array();
    
    function __construct($player1, $player2, $maps)
    {
        $this->turn = rand(0,1);
        $this->player = array($player1,$player2);
        foreach($this->player as &$p)
            $p = str_replace(' ','_',$p);
        $this->maps = $maps;
    }
    
    function next_round()
    {
        $this->turn = ($this->turn+1) % count($this->player);
    }
    
    function pick_drops()
    {
        $arr = array();
        for ( $i = 0; $i < count($this->maps); $i++ )
        {
            $arr []= $i >= count($this->maps) - $this->pick_num ? 'Pick' : 'Drop';
        }
        return implode('-',$arr);
    }
    
    function is_picking()
    {
        return count($this->maps) <= $this->pick_num;
    }
    
    private function find_map_index($map)
    {
        if ( strlen($map) == 0 )
            return false;
            
        $lowmaps = array_map('strtolower',$this->maps);
        $map = strtolower($map);
        
        for ( $i = 0; $i < count($lowmaps); $i++ )
        {
            if ( strncmp($lowmaps[$i], $map, strlen($map)) == 0 ) 
                return $i;
        }
        
        return false;
    }
    
    function has_map($map)
    {
        return $this->find_map_index($map) !== false;
    }
    
    function choose($map)
    {
        if ( ($k = $this->find_map_index($map)) !== false )
        {
            if ( $this->is_picking() )
                $this->picks []= $this->maps[$k];
            array_splice($this->maps,$k,1);
            return true;
        }
        return false;
    }
    
    function current_player()
    {
        return $this->player[$this->turn];
    }
    
    function is_player($player) 
    {
        foreach ( $this->player as $p )
            if ( $player == $p )
                return true;
        return false;
    }
}

class MatchPlayer
{
    public $name, $id, $score;
    function __construct($id, $name, $score=array())
    {
        $this->name =$name;
        $this->id = $id;
        $this->score = $score;
    }
    
    function add_score($score)
    {
        $this->score[]= sprintf("%02d",$score);
    }
}

class Match
{
    public $id, $label;
    public $team1, $team2;
    public $score1=array(), $score2=array();
    
    function __construct($id, $label, MatchPlayer $team1, MatchPlayer $team2)
    {
        $this->id = $id;
        $this->label = $label;
        $this->team1 = $team1;
        $this->team2 = $team2;
    }
    
    function team1()
    {
        return $this->team1->name;
    }
    function team2()
    {
        return $this->team2->name;
    }
    
    function score1()
    {
        return implode(', ',$this->team1->score);
    }
    function score2()
    {
        return implode(', ',$this->team2->score);
    }
    
    function winner()
    {
        $sum1 = 0;
        $sum2 = 0;
        $tot = 0;
        for ( $i = 0; $i < count($this->team1->score); $i++ )
        {
            $sum1 += $this->team1->score[$i];
            $sum2 += $this->team2->score[$i];
            if ( $this->team1->score[$i] > $this->team2->score[$i] )
                $tot++;
            else if ( $this->team1->score[$i] < $this->team2->score[$i] )
                $tot--;
        }
        if ( $tot > 0 )
            return $this->team1;
        else if ( $tot < 0 )
            return $this->team2;
        else if ( $sum1 > $sum2 )
            return $this->team1;
        else if ( $sum1 < $sum2 )
            return $this->team2;
        else
            return null;
    }
}

class Cup
{
    private $manager;
    public $description;
    public $url;
    public $id;
    public $maps;
    public $start_time;
    public $name;
    
    function __construct($manager,$id,$url,$name)
    {
        $this->manager = $manager;
        $this->id = $id;
        $this->url = $url;
        $this->name = $name;
        $this->maps = array();
    }
    
    function result_url()
    {
        return $this->manager->result_url.$this->url;
    }
    
    function add_map($map)
    {
        if ( !in_array($map,$this->maps) )
            $this->maps []= $map;
    }
    
    function remove_map($map)
    {
        if ( ($k = array_search($map,$this->maps)) !== false )
            array_splice($this->maps,$k,1);
    }
    
    function start()
    {
       return  $this->manager->start_tournament($this->id);
    }
}

class CupManager
{
    private $api_key;
    public $result_url = "http://challonge.com/";
    public $api_url = "https://api.challonge.com/v1/";
    public $organization;
    public $score_cache = array();
    
    function __construct($api_key,$organization=null)
    {
        $this->api_key = $api_key;
        $this->organization = $organization;
        if ( $organization != null )
            $this->result_url = "http://$organization.challonge.com/";
    }
    
    private function call($command,$params=array())
    {
        $params['api_key'] = $this->api_key;
        $url = $this->api_url."$command.json?";
        foreach($params as $k => $p)
            $url .= "$k=".urlencode($p)."&";
        return json_decode(file_get_contents($url),true);
    }
    
    
    private function set($command,$params=array())
    {
        $params['_method']="put";
        $params['api_key'] = $this->api_key;
        $options = array(
            'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($params),
            ),
        );
        $context  = stream_context_create($options);
        $url = $this->api_url."$command.json";
        return json_decode(file_get_contents($url,false,$context),true);
    }
    
    private function cup_from_json($json_array)
    {
        if ( !isset($json_array['tournament'] ) )
            return null;
            
        $cup = new Cup($this,
                        $json_array['tournament']['id'],
                        $json_array['tournament']['url'],
                        $json_array['tournament']['name']);
                        
        $matches=array();
        if ( preg_match("{".
                "<p>([^\n]*)</p>".
                "(\s*<p>Maps:\s*([-._a-zA-Z0-9]+(,\s*[-._a-zA-Z0-9]+)*)?</p>)?".
                "(\s*<p>Time:\s*([^\n]+)</p>)?".
                "}",
                $json_array['tournament']['description'],
                $matches
            ) )
        {
            $cup->description = $matches[1];
            if ( count($matches) > 3 )
				$cup->maps = explode(', ',$matches[3]);
            if ( count($matches) > 6 )
                $cup->start_time=strtotime($matches[6]);
            //$cup->start_time = $matches;
        }
        
        return $cup;

    }
    
    private function match_from_json($cup_id,$json_array)
    {
        if ( !isset($json_array['match'] ) )
            return null;
        
        $p1_id = $json_array['match']['player1_id'];
        $p2_id = $json_array['match']['player2_id'];
        $p1 = new MatchPlayer($p1_id,$this->participant($cup_id,$p1_id));
        $p2 = new MatchPlayer($p2_id,$this->participant($cup_id,$p2_id));
        
        $match_id  = $json_array['match']['id'];
        if ( isset($this->score_cache[$match_id]) )
        {
            $p1->score = $this->score_cache[$match_id][1];
            $p2->score = $this->score_cache[$match_id][2];
        }
        else
        {
            $scores = explode(',',$json_array['match']['scores_csv']);
            foreach($scores as $s)
            {
                $ls = explode('-',$s);
                if ( count($ls) == 2 )
                {
                    $p1->add_score($ls[0]);
                    $p2->add_score($ls[1]);
                }
            }
            $this->score_cache[$match_id] = array(1=>$p1->score,2=>$p2->score);
        }
        
        return new Match ($match_id, $json_array['match']['identifier'],$p1,$p2);
    }
    
    function tournaments($condition=array())
    {
        if ( isset($this->organization) )
            $condition['subdomain']='eac';
        $cond1 = array_merge($condition,array('state'=>'in_progress'));
        $ts1 = $this->call('tournaments',$cond1);
        $cond2 = array_merge($condition,array('state'=>'pending'));
        $ts2 = $this->call('tournaments',$cond2);
        $tarr = array();
        foreach($ts1 as $t)
            $tarr []= $this->cup_from_json($t);
        foreach($ts2 as $t)
            $tarr []= $this->cup_from_json($t);
        return $tarr;
    }
    
    function tournament($id)
    {
        return $this->cup_from_json($this->call("tournaments/$id"));
    }
    
    
    
    function start_tournament($id)
    {
        return $this->call("tournaments/start/$id");
    }
    
    function open_matches($cup_id)
    {
        $m = $this->call("tournaments/$cup_id/matches",array('state'=>'open'));
        $matches = array();
        foreach($m as $match)
            $matches[]= $this->match_from_json($cup_id,$match);
        return $matches;
    }
    
    function participant($cup_id,$id)
    {
         $p = $this->call("tournaments/$cup_id/participants/$id");
         if ( isset($p["participant"]) )
            return $p["participant"]["name"];
        return null;
    }
    
    function match($cup_id,$match_id)
    {
       return $this->match_from_json($cup_id,
                    $this->call("tournaments/$cup_id/matches/$match_id")
            );
    }
    
    function update_cup(Cup $cup)
    {
        $desc = "<p>{$cup->description}</p>\n".
                "<p>Maps: ".implode(", ",$cup->maps)."</p>\n".
                "<p>Time: ".date("c",$cup->start_time)."</p>";
        return $this->set("tournaments/{$cup->id}",array('tournament[description]'=>$desc));
    }
    
    function update_match(Cup $cup, Match $match)
    {
        $this->score_cache[$match->id] = array(1=>$match->team1->score,2=>$match->team2->score);
    }
    
    function end_match(Cup $cup, Match $match)
    {
        $params = array();
        $scores = array();
        for($i = 0; $i < count($match->team1->score); $i++ )
            $scores []= ((int)$match->team1->score[$i]).'-'.((int)$match->team2->score[$i]);
        $params['match[scores_csv]'] = implode(',',$scores);
        /*$params['games[][player1_score]']=$match->team1->score;
        $params['games[][player2_score]']=$match->team2->score;*/
        //if ( $match->winner != null )
        $win = $match->winner();
        if ( $win == null )
            $params['match[winner_id]'] = 'tie';
        else
            $params['match[winner_id]'] = $win->id;
        print_r($params);
        return $this->set("tournaments/{$cup->id}/matches/{$match->id}",$params);
    }
}

