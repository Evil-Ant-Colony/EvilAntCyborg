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
 
/**
 * \brief A participant in the cup
 * \property $name  Challonge name
 * \property $id    API ID
 * \property $nick  IRC Nick for this player
 */
class CupParticipant
{
    public $name, $id, $nick;
    
    function __construct($name, $id, $nick=null)
    {
        $this->name = $name;
        $this->id = $id;
        $this->nick = $nick ? $nick : $name;
    }
    
    /**
     * \brief Show name and (if it differs) the nick
     */
    function long_name()
    {
        return $this->name . ( $this->name != $this->nick ? "[{$this->nick}]" : "" );
    }
}

/**
 * \brief A participant involved in a particular match
 */
class MatchPlayer extends CupParticipant
{
    public $score; ///< Array with scores for each match
    function __construct(CupParticipant $participant, $score=array())
    {
        
        $this->name = $participant->name;
        $this->nick = $participant->nick;
        $this->id = $participant->id;
        $this->score = $score;
    }
    
    /**
     * \brief Add scores to the player
     */
    function add_score($score)
    {
        $this->score[]= sprintf("%02d",$score);
    }
}

/**
 * \brief Map picking helper
 */
class MapPicker
{
    public $players,        ///< Array of active players
           $maps,           ///< List of maps to choose from
           $turn,           ///< Index in $players representing the current turn for picking
           $pick_num = 1,   ///< Number of maps to pick (ie not drop)
           $picks = array();///< Chosen maps
    
    /**
     * \param $player1 One of the two players
     * \param $player2 One of the two players
     * \param $maps    Array of maps
     */
    function __construct(MatchPlayer $player1, MatchPlayer $player2, array $maps)
    {
        $this->turn = rand(0,1);
        $this->players = array($player1,$player2);
        foreach($this->players as &$p)
            $p->nick = str_replace(' ','_',$p->nick);
        $this->maps = $maps;
    }
    
    /**
     * \brief Advance turn to the next round
     */
    function next_round()
    {
        $this->turn = ($this->turn+1) % count($this->players);
    }
    
    /**
     * \brief Show a string in the form Pick-Pick-Drop, according to the picker settings
     */
    function pick_drops()
    {
        $arr = array();
        for ( $i = 0; $i < count($this->maps); $i++ )
        {
            $arr []= $i >= count($this->maps) - $this->pick_num ? 'Pick' : 'Drop';
        }
        return implode('-',$arr);
    }
    
    /**
     * \brief Whether it's picking rather than dropping
     */
    function is_picking()
    {
        return count($this->maps) <= $this->pick_num;
    }
    
    /**
     * \brief Get map index by name
     */
    private function find_map_index($map)
    {
        if ( strlen($map) == 0 )
            return false;
            
        $lowmaps = array_map('strtolower',$this->maps);
        $map = strtolower($map);
        
        for ( $i = 0; $i < count($lowmaps); $i++ )
        {
            if ( strncasecmp($lowmaps[$i], $map, strlen($map)) == 0 ) 
                return $i;
        }
        
        return false;
    }
    
    /**
     * \brief Whether it contains the given map
     */
    function has_map($map)
    {
        return $this->find_map_index($map) !== false;
    }
    
    /**
     * \brief Choose the given map for drop or picking
     */
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
    
    /**
     * \brief Get the current player
     */
    function current_player()
    {
        return $this->players[$this->turn];
    }
    
    /**
     * \brief Whether the given player is a player for this match
     * \param $player An object containing $nick or a string
     */
    function is_player($player) 
    {
        if ( is_object($player) )
            $player = $player->nick;
            
        foreach ( $this->players as $p )
            if ( $player == $p->nick )
                return true;
        return false;
    }
}

/**
 * \brief A match (only supports 2 players)
 */
class Match
{
    public $id;     ///< API ID
    public $players;  ///< Players
    
    function __construct($id, MatchPlayer $team1, MatchPlayer $team2)
    {
        $this->id = $id;
        $this->players = array($team1,$team2);
    }
    
    function team1()
    {
        return $this->players[0]->name;
    }
    function team2()
    {
        return $this->players[1]->name;
    }
    
    function score1()
    {
        return implode(', ',$this->players[0]->score);
    }
    function score2()
    {
        return implode(', ',$this->players[1]->score);
    }
    
    /**
     * \brief Determine the winning player based on their score
     * \return The winning player or \c null if they are tied
     */
    function winner()
    {
        $sum1 = 0;
        $sum2 = 0;
        $tot = 0;
        for ( $i = 0; $i < count($this->players[0]->score); $i++ )
        {
            $sum1 += $this->players[0]->score[$i];
            $sum2 += $this->players[1]->score[$i];
            if ( $this->players[0]->score[$i] > $this->players[1]->score[$i] )
                $tot++;
            else if ( $this->players[0]->score[$i] < $this->players[1]->score[$i] )
                $tot--;
        }
        if ( $tot > 0 )
            return $this->players[0];
        else if ( $tot < 0 )
            return $this->players[1];
        else if ( $sum1 > $sum2 )
            return $this->players[0];
        else if ( $sum1 < $sum2 )
            return $this->players[1];
        else
            return null;
    }
}

/**
 * \brief A tournament
 */
class Cup
{
    private $manager;
    public $description;
    public $url;
    public $id;
    public $maps;
    public $start_at;
    public $started_at;
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
    
    function participants($refresh = false)
    {
        return  $this->manager->get_participants($this->id);
    }
    
    function participant($p_id)
    {
        return $this->manager->participant($this->id,$p_id);
    }
    
    function started()
    {
        return $this->started_at != null && $this->started_at < time();
    }
}

/**
 * \brief Manage Cups and the challonge API
 */
class CupManager
{
    private $api_key;                                   ///< API Key
    public $result_url = "http://challonge.com/";       ///< Base URL for bracket display
    public $api_url = "https://api.challonge.com/v1/";  ///< API base URL
    public $organization;                               ///< Organization/prefix
    public $score_cache = array();                      ///< Internal scores (used because setting the score ends the relative match)
    
    function __construct($api_key,$organization=null)
    {
        $this->api_key = $api_key;
        $this->organization = $organization;
        if ( $organization != null )
            $this->result_url = "http://$organization.challonge.com/";
    }
    
    /**
     * \brief Get a result from the API
     * \param $command API command
     * \param $params Associative array of arguments to pass to the command
     * \return An associative array with the API result (extracted from JSON)
     */
    protected function get($command,$params=array())
    {
        $params['api_key'] = $this->api_key;
        $url = $this->api_url."$command.json?".http_build_query($params);
        Logger::log("std","<","\x1b[1mGET\x1b[0m $url",4);
        return json_decode(file_get_contents($url),true);
    }
    
    /**
     * \brief Update an object via the API (PUT)
     * \param $command API command
     * \param $params Associative array of arguments to pass to the command
     * \return An associative array with the API result (extracted from JSON)
     */
    protected function put($command,$params=array())
    {
        $params['_method']="put";
        $params['api_key'] = $this->api_key;
        $query = http_build_query($params);
        $options = array(
            'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n".
                             "Content-Length: ".strlen($query)."\r\n",
                'method'  => 'POST',
                'content' => $query,
            ),
        );
        $context  = stream_context_create($options);
        $url = $this->api_url."$command.json";
        Logger::log("std","<","\x1b[1mPUT\x1b[0m $url $query",4);
        return json_decode(file_get_contents($url,false,$context),true);
    }
    
    
    /**
     * \brief Create an object via the API (POST)
     * \param $command API command
     * \param $params Associative array of arguments to pass to the command
     * \return An associative array with the API result (extracted from JSON)
     */
    protected function post($command,$params=array())
    {
        $params['api_key'] = $this->api_key;
        $query = http_build_query($params);
        $options = array(
            'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n".
                             "Content-Length: ".strlen($query)."\r\n",
                'method'  => 'POST',
                'content' => $query,
            ),
        );
        $context  = stream_context_create($options);
        $url = $this->api_url."$command.json";
        Logger::log("std","<","\x1b[1mPOST\x1b[0m $url $query",4);
        $temp = file_get_contents($url,false,$context);
        return json_decode($temp,true);
    }
    
    /**
     * \brief Delete an object via the API (DELETE)
     * \param $command API command
     * \param $params Associative array of arguments to pass to the command
     * \return An associative array with the API result (extracted from JSON)
     * \todo Merge these 4 functions and add a string parameter for the method
     */
    protected function delete($command,$params=array())
    {
        $params['api_key'] = $this->api_key;
        $query = http_build_query($params);
        $options = array(
            'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n".
                             "Content-Length: ".strlen($query)."\r\n",
                'method'  => 'DELETE',
                'content' => $query,
            ),
        );
        $context  = stream_context_create($options);
        $url = $this->api_url."$command.json";
        Logger::log("std","<","\x1b[1mDELETE\x1b[0m $url $query",4);
        $temp = file_get_contents($url,false,$context);
        return json_decode($temp,true);
    }
    
    /**
     * \brief Create a cup object from the array received from the API
     */
    protected function cup_from_json($json_array)
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
                "}",
                $json_array['tournament']['description'],
                $matches
            ) )
        {
            $cup->description = $matches[1];
            if ( count($matches) > 3 )
                $cup->maps = explode(', ',$matches[3]);
        }
        
        if ( isset( $json_array['tournament']["start_at"] ) )
            $cup->start_at=strtotime($json_array['tournament']["start_at"]);
        if ( isset( $json_array['tournament']["started_at"] ) )
            $cup->started_at=strtotime($json_array['tournament']["started_at"]);
        
        return $cup;

    }
    
    /**
     * \brief Create a match object from the array received from the API
     */
    protected function match_from_json($cup_id,$json_array)
    {
        if ( !isset($json_array['match'] ) )
            return null;
        
        $p1_id = $json_array['match']['player1_id'];
        $p2_id = $json_array['match']['player2_id'];
        $p1 = new MatchPlayer($this->participant($cup_id,$p1_id));
        $p2 = new MatchPlayer($this->participant($cup_id,$p2_id));
        
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
        
        return new Match ($match_id, $p1,$p2);
    }
    
    
    /**
     * \brief Create a participant object from the array received from the API
     */
    protected function participant_from_json($json_array)
    {
        if ( isset($json_array["participant"]) )
            return new CupParticipant(
                $json_array["participant"]["name"],
                $json_array["participant"]["id"],
                $json_array["participant"]["misc"]
            );
        return null;
    }
    
    /**
     * \brief Get the tournaments which are pending in_progress
     * \param $condition Associative array of condition arguments
     * \return An array with the matched tournaments
     * \note Calls the API
     */
    function tournaments($condition=array())
    {
        if ( isset($this->organization) )
            $condition['subdomain']='eac';
        $cond1 = array_merge($condition,array('state'=>'in_progress'));
        $ts1 = $this->get('tournaments',$cond1);
        $cond2 = array_merge($condition,array('state'=>'pending'));
        $ts2 = $this->get('tournaments',$cond2);
        $tarr = array();
        foreach($ts1 as $t)
            $tarr []= $this->cup_from_json($t);
        foreach($ts2 as $t)
            $tarr []= $this->cup_from_json($t);
        return $tarr;
    }
    
    
    /**
     * \brief Get a cup from its ID
     * \note Calls the API
     */
    function tournament($id)
    {
        return $this->cup_from_json($this->get("tournaments/$id"));
    }
    
    
    /**
     * \brief Marks the end of the configuration of the cup and the beginning of the matches
     * \param $id Cup ID
     * \note Calls the API
     */
    function start_tournament($id)
    {
        return $this->get("tournaments/start/$id");
    }
    
    /**
     * \brief Get open matches
     * \param $cup_id Cup ID
	 * \param $max max number of results
     * \note Calls the API
     */
    function open_matches($cup_id,$max=-1)
    {
        $m = $this->get("tournaments/$cup_id/matches",array('state'=>'open'));
        $matches = array();
        $i = 0;
        foreach($m as $match)
        {
            $matches[]= $this->match_from_json($cup_id,$match);
            if ( $max > 0 && ++$i >= $max )
                break;
        }
        return $matches;
    }
    
    /**
     * \brief Get participant by ID
     * \param $cup_id   Cup id
     * \param $id       Participant id
     * \return Participant object or \c null if not found
     * \note Calls the API
     */
    function participant($cup_id,$id)
    {
        return $this->participant_from_json($this->get("tournaments/$cup_id/participants/$id"));
    }
    
    /**
     * \brief Add a participant in the given cup
     * \note Calls the API
     */
    function add_participant($cup_id,CupParticipant $part)
    {
		return $this->participant_from_json(
				$this->post("tournaments/$cup_id/participants",array(
					'participant[name]'=>$part->name,
					'participant[misc]'=>$part->nick,
				)));
    }
    
    /**
     * \brief Delete a participant
     * \note Calls the API
     */
    function remove_participant($cup_id,CupParticipant $part)
    {
		return $this->delete("tournaments/$cup_id/participants/{$part->id}");
    }
    
    /**
     * \brief Get match by ID
     * \param $cup_id   Cup id
     * \param $match_id Match id
     * \return Match object or \c null if not found
     * \note Calls the API
     */
    function match($cup_id,$match_id)
    {
        return $this->match_from_json($cup_id,
                    $this->get("tournaments/$cup_id/matches/$match_id")
            );
    }
    
    /**
     * \brief Save changes made to the cup  object
     * \note Calls the API
     */
    function update_cup(Cup $cup)
    {
        $desc = "<p>{$cup->description}</p>\n".
                "<p>Maps: ".implode(", ",$cup->maps)."</p>";
                
        $array = array();
        $array['tournament[description]']=$desc;
        if ( $cup->start_at != null )
        $array['tournament[start_at]'] = date("c",$cup->start_at);
        return $this->put("tournaments/{$cup->id}",$array);
    }
    
    /**
     * \brief Store scores for the given match
     */
    function update_match(Cup $cup, Match $match)
    {
        $this->score_cache[$match->id] = array( 1 => $match->players[0]->score,
                                                2 => $match->players[1]->score);
    }
    
    /**
     * \brief Save scores of the match and set the winner
     * \note Calls the API
     */
    function end_match(Cup $cup, Match $match)
    {
        $params = array();
        $scores = array();
        for($i = 0; $i < count($match->players[0]->score); $i++ )
            $scores []= ((int)$match->players[0]->score[$i]).'-'.((int)$match->players[1]->score[$i]);
        $params['match[scores_csv]'] = implode(',',$scores);
        
        $win = $match->winner();
        if ( $win == null )
            $params['match[winner_id]'] = 'tie';
        else
            $params['match[winner_id]'] = $win->id;
        return $this->put("tournaments/{$cup->id}/matches/{$match->id}",$params);
    }
}

