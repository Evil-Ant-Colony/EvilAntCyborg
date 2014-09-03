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

require_once('misc/cupmanager.php');

/**
 * \brief cup manager that keeps some off-line info
 */
class CachedCupManager extends CupManager
{
	public $cups;				///< Array of open cups
	private $current_cup;		///< Reference to the current cup
	public $map_picking_status; ///< Picking status: 0 = NO, 1 = SETUP, 2 = PICKING
	public $map_picker;			///< Active map picker
	public $participants=array();///< Cached participants
	
	function __construct($api_key,$organization=null)
	{
		parent::__construct($api_key,$organization);
		$this->map_picking_status = 0;
	}
	
	/**
	 * \brief Reload trounament information
     * \note Calls the API
     */
	function update_tournaments()
	{
		$this->participants=array();
		$this->cups = $this->tournaments();
		if ( empty($this->cups) )
			$this->select_cup(null);
		else if ( $this->current_cup == null )
			$this->select_cup($this->cups[0]);
		else
		{
			foreach ( $this->cups as $cup )
			{
				if ( $cup->id == $this->current_cup->id )
				{
					$this->select_cup($cup);
					return;
				}
			}
			$this->select_cup($this->cups[0]);
		}
	}
	
	/**
	 * \brief Select the given cup and ensure all the info are loaded
	 */
	function select_cup(Cup $cup)
	{
	
		$this->current_cup = $cup;
		if ( $cup )
			$this->reload_participants($cup->id);
	}
	
	/**
	 * \brief Get the selected cup
	 */
	function cup()
	{
		return $this->current_cup;
	}
	
	/**
	 * \brief Reload participants of the given cup
	 */
	function reload_participants($cup_id)
	{
		$this->participants[$cup_id] = array();
		$r = $this->get("tournaments/$cup_id/participants");
		if ( is_array($r) )
			foreach($r as $p)
			{
				$part = $this->participant_from_json($p);
				if ( $part != null )
					$this->participants[$cup_id][$part->id] = $part;
			}
	}
	
	/**
	 * \brief Check if there's a cup set
	 * 
	 * If there is no set cup but one is available, that one is selected
	 * \return Whethere there's an active cup
	 */
	function check_cup()
	{   
		if ( $this->current_cup != null )
			return true;
		if ( empty($this->cups) )
			return false;
		else
			$this->current_cup = $this->cups[0];
		return true;
	}
	
	/**
	 * \brief Get open matches for the current cup
	 * \pre \code $this->check_cup() \endcode
	 * \param $max max number of results
	 * \note Calls the API
	 */
	function current_open_matches($max=-1)
	{
		return $this->open_matches($this->current_cup->id,$max);
	}
	
	/**
	 * \brief Whether the current cup exists and it has started
	 */
	function has_started()
	{
		return $this->current_cup != null && $this->current_cup->started();
	}
	
    /**
     * \brief Get participant by ID
     * \param $cup_id   Cup id
     * \param $id       Participant id
     * \return Participant object or \c null if not found
     * \note Checks the cache before calling the API
     */
	function participant($cup_id,$id)
	{
		if ( !empty($this->participants[$cup_id][$id]) )
			return $this->participants[$cup_id][$id];
		if ( !isset($this->participants[$cup_id]) )
			$this->participants[$cup_id] = array();
		$part = parent::participant($cup_id,$id);
		if ( $part != null )
			$this->participants[$cup_id][$id] = $part;
		return $part;
	}
	
	/**
	 * \brief Get cached participants for the current cup
	 */
	function participants()
	{
		if ( !isset($this->participants[$this->current_cup->id]) )
			$this->participants[$this->current_cup->id] = array();
		return $this->participants[$this->current_cup->id];
	}
	
	
	
}

/**
 * \brief Base class for cup executors
 */
abstract class Executor_Cup extends CommandExecutor
{
	public $cup_manager; ///< Cup manager
	public $on_picking;  ///< Picking stage this executor is active on
	public $writes=false;///< Whether the executors writes to the cup (ie: can only be executed before it has started)
	
	function __construct(CachedCupManager $cup_manager, 
						$name,$auth,$synopsis="",$description="",$irc_cmd='PRIVMSG')
	{
		parent::__construct($name,$auth,$synopsis,$description,$irc_cmd);
		$this->cup_manager = $cup_manager;
		$this->on_picking = 0;
	}
	
	/**
	 * \brief Whether is should be active according to the picking stage
	 */
	function check_picking()
	{
		return $this->on_picking == $this->cup_manager->map_picking_status;
	}
	
	function check_auth($nick,$host,MelanoBot $bot, BotData $data)
	{
		return $this->check_picking() && parent::check_auth($nick,$host,$bot, $data);
	}
	
	function check(MelanoBotCommand $cmd,MelanoBot $bot,BotData $data)
	{
		return  ( !$this->writes || !$this->cup_manager->has_started() ) && 
				$this->check_auth($cmd->from,$cmd->host,$bot,$data);
	}
	
	/**
	 * \brief Check whether there's an active cup, if not send an error message over IRC
	 * \return \c true if there's a cup
	 */
	function check_cup(MelanoBotCommand $cmd, MelanoBot $bot)
	{
		if ( !$this->cup_manager->check_cup() )
		{
			$bot->say($cmd->channel,"No tournament is currently scheduled",1024);
			return false;
		}
		return true;
	}
	
	/**
	 * \brief Get active cup
	 */
	function cup()
	{
		return $this->cup_manager->cup();
	}
	
	/**
	 * \brief Get active map_picker
	 */
	function map_picker()
	{
		return $this->cup_manager->map_picker;
	}
	
	/**
	 * \brief Show a message for map picking
	 * \todo Why is this here?
	 */
	function map_pick_show_turn($cmd,$bot)
	{
		$dp = $this->map_picker()->is_picking() ? "\x0303PICK\x03" : "\x0304DROP\x03";
		$bot->say($cmd->channel,$this->map_picker()->current_player()->nick.", your turn",1024);
		$bot->say($cmd->channel,"$dp ".implode(', ',$this->map_picker()->maps),1024);
	}
}

/**
 * \brief Switch executors with the same name (eg: one for admins and one for users)
 */
abstract class Executor_Multi_Cup extends Executor_Cup
{
	private $multiple_inheritance;
	
	function __construct(CachedCupManager $cup_manager,$name,$auth,$executors,
		$synopsis="",$description="",$irc_cmd='PRIVMSG' )
	{
		parent::__construct($cup_manager,$name,$auth,$synopsis,$description,$irc_cmd);
		$this->multiple_inheritance = new Executor_Multi ($name,$executors);
	}
	
	
	function check_auth($nick,$host,MelanoBot $bot, BotData $data)
	{
		return $this->check_picking() && $this->multiple_inheritance->check_auth($nick,$host,$bot,$data);
	}
	
	
	function check(MelanoBotCommand $cmd,MelanoBot $bot,BotData $data)
	{
		return $this->check_picking() && $this->multiple_inheritance->check($cmd,$bot,$data);
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		return $this->multiple_inheritance->execute($cmd,$bot,$data);
	}
	
	function executors()
	{
		return $this->multiple_inheritance->executors;
	}
	
	function help(MelanoBotCommand $cmd,MelanoBot $bot,BotData $data)
	{
		return $this->multiple_inheritance->help($cmd,$bot,$data);
	}
}

/**
 * \brief Show next matches
 */
class Executor_Cup_Next extends Executor_Cup
{
	function __construct(CachedCupManager $cup_manager)
	{
		parent::__construct($cup_manager,'next',null,'next [n]','Show the next (n) scheduled matches');
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		if ( $this->check_cup($cmd,$bot) )
		{
			$num = isset($cmd->params[0]) ? (int)$cmd->params[0] : 1;
			if ( $num > 5 && !$data->user_in_list('admin',$bot->get_user($cmd->from,$cmd->host)) )
				$num = 5;
			if ( $num > 1 )
				$bot->say($cmd->channel,"Fetching...",1025);
			$matches = $this->cup_manager->current_open_matches($num);
			if ( count($matches) == 0 )
			{
				$bot->say($cmd->channel,"No matches are currently available",1024);
			}
			else
			{
				for ( $i = 0; $i < count($matches); $i++ )
				{
					$match = $matches[$i];
					if ( $match == null )
						break;
					$bot->say($cmd->channel,
						$match->id.": ".$match->team1()." vs ".$match->team2(),1024);
				}
			}
		}
	}
}

/**
 * \brief Show (and update) available cups
 */
class Executor_Cup_Cups extends Executor_Cup
{
	function __construct(CachedCupManager $cup_manager)
	{
		parent::__construct($cup_manager,'cups','admin','cups','Show the available cups');
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		$this->cup_manager->update_tournaments();
		
		if ( empty($this->cup_manager->cups) )
			$bot->say($cmd->channel,"No cups available",1024);
		else
		{
			$text = "Available cups: ";
			foreach ( $this->cup_manager->cups as $c )
			{
				if ( $this->cup() != null && 
					 $c->id == $this->cup()->id )
					$text .= "*";
				$text .= "{$c->name} ({$c->id}), ";
			}
			$bot->say($cmd->channel,$text,1024);
		}
	}
}

/**
 * \brief Show the bracket URL
 * \todo Maybe rename to bracket
 */
class Executor_Cup_Results extends Executor_Cup
{
	function __construct(CachedCupManager $cup_manager)
	{
		parent::__construct($cup_manager,'bracket',null,'bracket',
			'Show a URL where you can view the cup details');
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		if ( $this->check_cup($cmd,$bot) )
			$bot->say($cmd->channel,$this->cup()->result_url(),1024);
	}
}

/**
 * \brief Set channel topic from cup properties
 */
class Executor_Cup_Topic extends Executor_Cup
{
	public $topic;
	public $topic_nocup;
	
	function __construct(CachedCupManager $cup_manager,
			$topic = "%name% %bracket%",
			$topic_nocup = "No cup is currently scheduled",
			$trigger = "topic"
		)
	{
		parent::__construct($cup_manager,$trigger,'admin',$trigger,
			'Update the channel topic according to the current cup');
		$this->topic = $topic;
		$this->topic_nocup = $topic_nocup;
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		$topic = $this->cup_manager->check_cup() ? 
			$this->cup()->format_string($this->topic) :
			$this->topic_nocup;
		$bot->command("TOPIC","{$cmd->channel} :$topic",1024);
	}
}

/**
 * \brief Show the current cup
 */
class Executor_Cup_CupReadonly extends Executor_Cup
{
	function __construct(CachedCupManager $cup_manager)
	{
		parent::__construct($cup_manager,'cup',null,'cup',
			'Show the current cup name and ID');
	}
	
	function check(MelanoBotCommand $cmd,MelanoBot $bot,BotData $data)
	{
		return count($cmd->params) == 0 || !$data->user_in_list('admin',$bot->get_user($cmd->from,$cmd->host)) ;
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		if ( $this->check_cup($cmd,$bot) )
			$bot->say($cmd->channel,
				"Current cup: ".$this->cup()->name." - ".$this->cup()->id,1024);
	}
}

/**
 * \brief Set the current cup
 */
class Executor_Cup_CupSelect extends Executor_Cup
{
	function __construct(CachedCupManager $cup_manager)
	{
		parent::__construct($cup_manager,'cup','admin',
			"cup [cup_name|cup_id|property (set name value)|name|(string format)]",
			'Change the current cup');
	}
	
	function check(MelanoBotCommand $cmd,MelanoBot $bot,BotData $data)
	{
		return count($cmd->params) > 0 && $this->check_auth($cmd->from,$cmd->host,$bot,$data) ;
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		if ( count($cmd->params) > 1 && $cmd->params[0] == "property" )
		{
			if ( $this->check_cup($cmd,$bot) )
			{
				if ( count($cmd->params) > 3 && $cmd->params[1] == "set" )
				{
					$this->cup()->properties[$cmd->params[2]] = $cmd->param_string(false,3);
					$this->cup_manager->update_cup($this->cup());
					$bot->say($cmd->channel,"Updated cup",1024);
				}
				else if ( count($cmd->params) > 2 && $cmd->params[1] == "string" )
					$bot->say($cmd->channel,$this->cup()->format_string($cmd->param_string(false,2)),1024);
				else if ( count($cmd->params) == 2 )
				{
					$name = $cmd->params[1];
					if ( isset($this->cup()->properties[$name]) )
						$bot->say($cmd->channel,"$name: ".$this->cup()->properties[$name],1024);
					else
						$bot->say($cmd->channel,"Property $name not found",1024);
				}
				else
					$bot->say($cmd->channel,"Malformed arguments",1024);
			}
		}
		else
		{
			$next = trim($cmd->param_string());
			$cup = null;
			foreach($this->cup_manager->cups as $c)
			{
				if ( $c->id == $next || $c->name == $next )
				{
					$cup = $c;
					$this->cup_manager->select_cup($c);
					break;
				}
			}
			if ( $cup )
				$bot->say($cmd->channel,"Cup switched to: {$cup->name} - {$cup->id}",1024);
			else
				$bot->say($cmd->channel,"Cup \"$next\" not found",1024);
		}
	}
	
}

/**
 * \brief Executor_Cup_CupReadonly + Executor_Cup_CupSelect
 */
class Executor_Cup_Cup extends Executor_Multi_Cup
{
	function __construct(CachedCupManager $cup_manager)
	{
		parent::__construct($cup_manager,'cup',null,array(
			new Executor_Cup_CupSelect($cup_manager),
			new Executor_Cup_CupReadonly($cup_manager),
		));
	}
}


class Executor_Cup_DescriptionReadonly extends Executor_Cup
{
	function __construct(CachedCupManager $cup_manager)
	{
		parent::__construct($cup_manager,'description',null,'description',
			'Show the description of the current cup');
	}
	
	function check(MelanoBotCommand $cmd,MelanoBot $bot,BotData $data)
	{
		return count($cmd->params) == 0 || !$data->user_in_list('admin',$bot->get_user($cmd->from,$cmd->host)) ;
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		if ( $this->check_cup($cmd,$bot) )
		{
			$cup = $this->cup();
			$bot->say($cmd->channel,"Cup {$cup->name} ({$cup->id}): {$cup->description}",1024);
		}
	}
}

class Executor_Cup_DescriptionSet extends Executor_Cup
{
	function __construct(CachedCupManager $cup_manager)
	{
		parent::__construct($cup_manager,'description','admin','description [new_description]',
			'Change the description for the current cup');
		$this->writes = true;
	}
	
	function check(MelanoBotCommand $cmd,MelanoBot $bot,BotData $data)
	{
		return count($cmd->params) > 0 && parent::check($cmd,$bot,$data) ;
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		if ( $this->check_cup($cmd,$bot) )
		{
			$cup = $this->cup();
			$cup->description = $cmd->param_string();
			$this->cup_manager->update_cup($cup);
			$bot->say($cmd->channel,"Cup {$cup->name} ({$cup->id}): {$cup->description}",1024);
		}
	}
}


class Executor_Cup_Description extends Executor_Multi_Cup
{
	function __construct(CachedCupManager $cup_manager)
	{
		parent::__construct($cup_manager,'description',null,array(
			new Executor_Cup_DescriptionSet($cup_manager),
			new Executor_Cup_DescriptionReadonly($cup_manager),
		));
	}
}

class Executor_Cup_TimeReadonly extends Executor_Cup
{
	function __construct(CachedCupManager $cup_manager)
	{
		parent::__construct($cup_manager,'time',null,'time',
			'Display the scheduled start time for the current cup');
	}
	
	function check(MelanoBotCommand $cmd,MelanoBot $bot,BotData $data)
	{
		return count($cmd->params) == 0 || !$data->user_in_list('admin',$bot->get_user($cmd->from,$cmd->host)) ;
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		// noop
	}
}

class Executor_Cup_TimeSet extends Executor_Cup
{
	function __construct(CachedCupManager $cup_manager)
	{
		parent::__construct($cup_manager,'time','admin','time [new_time]',
			'Change the scheduled start time for the current cup');
		$this->writes = true;
	}
	
	function check(MelanoBotCommand $cmd,MelanoBot $bot,BotData $data)
	{
		return count($cmd->params) > 0 && parent::check($cmd,$bot,$data) ;
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		if ( $this->check_cup($cmd,$bot) )
		{
			$cup = $this->cup();
			
			$time = strtotime($cmd->param_string());
			if ( $time != null )
			{
				$cup->start_at = $time;
				$this->cup_manager->update_cup($cup);
			}
			else
				$bot->say($cmd->channel,"Invalid time format",1024);
		}
	}
}

class Executor_Cup_Time extends Executor_Multi_Cup
{

	function __construct(CachedCupManager $cup_manager)
	{
		parent::__construct($cup_manager,'time',null,array(
			new Executor_Cup_TimeSet($cup_manager),
			new Executor_Cup_TimeReadonly($cup_manager),
		));
	}
	
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		if ( $this->check_cup($cmd, $bot) )
		{
			parent::execute($cmd,$bot,$data);
			
			$cup = $this->cup();
			if ( $cup->started_at != null && $cup->start_at <= time() )
			{
				$bot->say($cmd->channel,"Cup already started",1024);
			}
			else if ( $cup->start_at == null )
				$bot->say($cmd->channel,"Current cup is currently not scheduled",1024);
			else if ( $cup->start_at <= time() )
				$bot->say($cmd->channel,"Cup is starting soon",1024);
			else
			{
				$delta = ceil( ($cup->start_at - time())/60 ) * 60;
				$d_day = (int) ($delta / (60*60*24));
				$d_hour = (int) ($delta % (60*60*24) / (60*60));
				$d_min = round($delta % (60*60) / 60);
				$d_string = "";
				if ( $d_day > 0 )
					$d_string .= "$d_day days, ";
				if ( $d_hour > 0 || $d_day > 0 )
					$d_string .= "$d_hour hours, ";
				$d_string .= "$d_min minutes";
				$bot->say($cmd->channel,"Cup will start in $d_string",1024);
			}
		}
	}
}

/**
 * \brief Show the map list (admins can add and remove them)
 */
class Executor_Cup_Maps extends Executor_Multi_Cup
{
	private $ex_write, $ex_read;
	
	function __construct(CachedCupManager $cup_manager)
	{
		parent::__construct($cup_manager,'maps',null,array(
			$this->ex_write = new Executor_MiscListEdit('maps','admin'),
			$this->ex_read = new Executor_MiscListReadonly('maps',null)
		));
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		if ( $this->check_cup($cmd,$bot) )
		{
			$cup = $this->cup();
			
			if ( count($cmd->params) > 0 && !$this->cup_manager->has_started() &&
				$this->ex_write->check($cmd,$bot,$data) )
			{
				$this->ex_write->list = &$cup->maps;
				$this->ex_write->execute($cmd,$bot,$data);
				$this->cup_manager->update_cup($cup);
			}
			else
			if ( $this->ex_read->check($cmd,$bot,$data) )
			{
				$this->ex_read->list = &$cup->maps;
				$this->ex_read->execute($cmd,$bot,$data);
			}
		}
	}
	
}

/**
 * \brief Show active players
 */
class Executor_Cup_PlayersReadonly extends Executor_Cup
{
	public $max_count = 8;
	
	function __construct(CachedCupManager $cup_manager)
	{
		parent::__construct($cup_manager,'players',null,'players [query]',
			'Show players matching query participating in the cup');
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		if ( $this->check_cup($cmd,$bot) )
		{
			$this->show($cmd->param_string(false),$cmd->channel,$bot);
		}
	}
	
	protected function show($query,$channel,MelanoBot $bot,$priority=1)
	{
		$players = $this->cup_manager->participants();
		if ( count($players) == 0 )
			$bot->say($channel,"No players in the cup",$priority);
		else
		{
			$matches = array();
			foreach ( $players as $play )
				if ( !$query || strncasecmp($play->name, $query, strlen($query)) == 0 )
					$matches[] = $play->name;
			if ( count($matches) == 0 )
				$bot->say($channel, "No matching players",$priority);
			else
			{
				$string = "Found \00310".count($matches)."\xf players";
				if ( count($matches) > $this->max_count )
					$string .= " (too many to show)";
				else
					$string .= ": ".implode(", ",$matches);
				$bot->say($channel,$string,$priority);
			
			}
		}
		
	}
}

/**
 * \brief Manage cup players
 */
class Executor_Cup_PlayersAdmin extends Executor_Cup_PlayersReadonly
{
	
	function __construct(CachedCupManager $cup_manager,$auth='admin')
	{
		parent::__construct($cup_manager);
		$this->auth = $auth;
		$this->synopsis='players reload|+ Player...|- Player...';
		$this->description='Manage cup players';
		$this->writes = true;
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		if ( !$this->check_cup($cmd,$bot) )
			return;
			
		if ( count($cmd->params) == 0 )
		{
			$this->show("",$cmd->channel,$bot,1024);
		}
		else if ( $cmd->params[0] == "+" || $cmd->params[0] == "add" )
		{
			$name = $cmd->param_string(false,1);
			foreach( $this->cup_manager->participants() as $p )
			{
				if ( $p->name == $name )
				{
					$bot->say($cmd->channel,"Player already in the cup",1024);
					return;
				}
			}
			$part = $this->cup_manager->add_participant($this->cup()->id,new CupParticipant($name,null));
			if ( $part != null )
			{
				$this->cup_manager->participants[$this->cup()->id][$part->id] = $part;
				$bot->say($cmd->channel,"Enrolled {$part->name} in the cup",1024);
			}
			else
				$bot->say($cmd->channel,"Error while adding $name to the cup",1024);
		}
		else if ( $cmd->params[0] == "-" || $cmd->params[0] == "rm" )
		{
			$name = $cmd->param_string(false,1);
			
			foreach( $this->cup_manager->participants() as $part )
			{
				if ( $part->name == $name || $part->id == $name || $part->nick == $name )
				{
					$r = $this->cup_manager->remove_participant($this->cup()->id,$part);
					unset($this->cup_manager->participants[$this->cup()->id][$part->id]);
					$bot->say($cmd->channel,"{$part->name} has been removed from the cup",1024);
					return;
				}
			}
			$bot->say($cmd->channel,"Player $name not found",1024);
		}
		else if ( $cmd->params[0] == "reload" )
		{
			$this->cup_manager->reload_participants($this->cup()->id);
			$this->show("",$cmd->channel,$bot,1024);
		}
		else
		{
			$this->show($cmd->param_string(false),$cmd->channel,$bot,1024);
		}
		
	}
}

/**
 * \brief Manage participants
 */
class Executor_Cup_Players extends Executor_Multi_Cup
{

	function __construct(CachedCupManager $cup_manager)
	{
		parent::__construct($cup_manager,'players',null,array(
			new Executor_Cup_PlayersAdmin($cup_manager),
			new Executor_Cup_PlayersReadonly($cup_manager),
		));
	}
}

/**
 * \brief Start the current cup
 */
class Executor_Cup_Start extends Executor_Cup
{
	function __construct(CachedCupManager $cup_manager)
	{
		parent::__construct($cup_manager,'start','admin','start',
			'Start the cup');
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		if ( $this->check_cup($cmd,$bot) )
		{
			$cup = $this->cup();
			$this->cup_manager->update_cup($cup);
			$cup->start();
			$cup->started_at = time();
			$bot->say($cmd->channel,"Cup started",1024);
		}
	}
}


class Executor_Cup_ScoreReadonly extends CommandExecutor
{
	function __construct()
	{
		parent::__construct('score',null,'score match_id',
			'Show the scores for match_id');
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		// noop
	}
}

class Executor_Cup_ScoreSet extends CommandExecutor
{
	function __construct()
	{
		parent::__construct('score','admin','score match_id score_1 score_2',
			'Appends the given scores to the score list in the given match');
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		// noop
	}
}

class Executor_Cup_Score extends Executor_Cup
{
	private $ro, $rw;
	
	function __construct(CachedCupManager $cup_manager)
	{
		parent::__construct($cup_manager,'score',null);
		$this->ro = new Executor_Cup_ScoreReadonly();
		$this->rw = new Executor_Cup_ScoreSet();
		//$this->reports_error = true;
	}
	
	function help(MelanoBotCommand $cmd,MelanoBot $bot,BotData $data)
	{
		if ( $this->rw->check_auth($cmd->from,$cmd->host,$bot,$data) )
		{
			$this->rw->help($cmd,$bot,$data);
		}
		else
			$this->ro->help($cmd,$bot,$data);
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		if ( $this->check_cup($cmd, $bot) )
		{
		
			if ( count($cmd->params) < 1 )
			{
				$bot->say($cmd->channel,"Which match?",1024);
				return;
			}
			
			$cup = $this->cup();
			$match = $this->cup_manager->match($cup->id,$cmd->params[0]);
			if ( $match == null )
			{
				$bot->say($cmd->channel,"Match ".$cmd->params[0]." not found",1024);
				return;
			}
			
			// matchID score1 score2
			if ( count($cmd->params) == 3 && $this->rw->check_auth($cmd->from,$cmd->host,$bot,$data) )
			{
				$match->players[0]->add_score($cmd->params[1]);
				$match->players[1]->add_score($cmd->params[2]);
				$bot->say($cmd->channel,"Updated match ".$cmd->params[0].":",1024);
				$this->cup_manager->update_match($cup,$match);
			}
			else
				$bot->say($cmd->channel,"Match {$match->id}:",1024);
				
			$t1 = $match->team1();
			$t2 = $match->team2();
			$len=max(strlen($t1),strlen($t2));
			$bot->say($cmd->channel,str_pad($t1,$len).": ".$match->score1(),1024);
			$bot->say($cmd->channel,str_pad($t2,$len).": ".$match->score2(),1024); 
		}
	}
}

/**
 * \brief Finalize a match
 */
class Executor_Cup_End extends Executor_Cup
{
	function __construct(CachedCupManager $cup_manager)
	{
		parent::__construct($cup_manager,'end','admin','end match_id',
			'End the given match and save score changes to challonge');
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		if ( count($cmd->params) != 1 )
		{
			$bot->say($cmd->channel,"Match ID?",1024);
		}
		else if ( $this->check_cup($cmd,$bot) )
		{
			$cup = $this->cup();
			$match = $this->cup_manager->match($cup->id,$cmd->params[0]);
			if ( $match == null )
			{
				$bot->say($cmd->channel,"Match ".$cmd->params[0]." not found",1024);
				return;
			}
			
			$win = $match->winner();
			if ( $win == null )
			{
				$bot->say($cmd->channel,"Cannot end ".$cmd->params[0]." (no winner)",1024);
				return;
			}
			
			$this->cup_manager->end_match($cup,$match);
			$bot->say($cmd->channel,"{$win->name} won match {$match->id} (".$match->team1()." vs ".$match->team2().")",1024);
		}
	}

}

/**
 * \brief Begin setting up picking for a match
 */
class Executor_Cup_Pick_Setup extends Executor_Cup
{
	function __construct(CachedCupManager $cup_manager)
	{
		parent::__construct($cup_manager,'setup','admin','setup match_id',
			'Start a map picking session');
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		if ( $this->check_cup($cmd,$bot) )
		{
			$cup = $this->cup();
			if ( empty($cmd->params) )
			{
				$bot->say($cmd->channel,"Match ID?",1024);
				return;
			}
			if ( empty($cup->maps) )
			{
				$bot->say($cmd->channel,"No maps to pick...",1024);
				return;
			}
			
			$match = $this->cup_manager->match($cup->id,$cmd->params[0]);
			if ( $match == null )
			{
				$bot->say($cmd->channel,"Match ".$cmd->params[0]." not found",1024);
				return;
			}
			
			$map_pick = new MapPicker($match->players[0],$match->players[1],$cup->maps);
			$this->cup_manager->map_picker = $map_pick;
			$this->cup_manager->map_picking_status = 1;
			$data->lists['player'] = array();
			
			$bot->say($cmd->channel, "Setting up picking for {$match->id}: ".
									$map_pick->players[0]->long_name()." vs ".
									$map_pick->players[1]->long_name(),1024);
			
		}
	}
	
	function check(MelanoBotCommand $cmd,MelanoBot $bot,BotData $data)
	{
		return count($cmd->params) == 1 && parent::check($cmd,$bot,$data);
	}

}


/**
 * \brief Change IRC nick to listen for map picking
 */
class Executor_Cup_Pick_Nick extends Executor_Cup
{
	function __construct(CachedCupManager $cup_manager)
	{
		parent::__construct($cup_manager,'nick','admin','nick [old new]',
			'Change IRC nick to listen for map picking');
		$this->on_picking = 1;
	}
	
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		if ( count($cmd->params) >= 2 )
		{
			$old = $cmd->param_string(false,0,count($cmd->params)-1);
			$new = $cmd->params[count($cmd->params)-1];
			foreach ( $this->map_picker()->players as &$p )
				if ( $old == $p->nick || $old == $p->name || $old == $p->id )
				{
					$p->nick = $new;
					
					if ( $player = $this->cup_manager->participant($this->cup()->id,$p->id) )
						$player->nick = $new;
					else
						 $this->cup_manager->participants[$this->cup()->id][$p->id] = new CupParticipant($p->name,$p->id,$p->nick);
						
					
					$bot->say($cmd->channel,"Listen to $new as map picker for {$p->name}",1024);
					return;
				}
			$bot->say($cmd->channel,"$old is not a player of this match",1024);
		}
		else
		{
			$players = array();
			foreach($this->map_picker()->players as $p)
				$players[] = $p->name == $p->nick ? $p->nick : "{$p->nick} (for {$p->name})";
			$bot->say($cmd->channel,"Currently listening to ".implode(' and ',$players),1024);
		}
	}
}

/**
 * \brief Set number of maps to pick (ie: not drop)
 */
class Executor_Cup_Pick_Pick extends Executor_Cup
{
	function __construct(CachedCupManager $cup_manager)
	{
		parent::__construct($cup_manager,'pick','admin','pick n',
			'Set the number of maps to pick (ie: not drop)');
		$this->on_picking = 1;
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		if ( count($cmd->params) >= 1 )
		{
			$this->map_picker()->pick_num = (int)$cmd->params[0];
			$bot->say($cmd->channel,"Map picking: ".$this->map_picker()->pick_drops(),1024);
		}
		else
		{
			$bot->say($cmd->channel,"How many picks?",1024);
		}
	}
}


/**
 * \brief End picking set up and start actual picking
 */
class Executor_Cup_Pick_Begin extends Executor_Cup
{
	function __construct(CachedCupManager $cup_manager)
	{
		parent::__construct($cup_manager,'begin','admin','begin',
			'Start the actual map picking (after setup)');
		$this->on_picking = 1;
	}
	
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
        
		$bot->say($cmd->channel,"Starting map picking ",1024);
		$bot->say($cmd->channel,$this->map_picker()->players[0]->long_name()." vs ".
								$this->map_picker()->players[1]->long_name(),1024);
		$bot->say($cmd->channel,$this->map_picker()->pick_drops(),1024);
		$data->lists['player'] = array();
		foreach ( $this->map_picker()->players as $player )
			$data->add_to_list('player',new IRC_User(null,$player->nick));
		$this->map_pick_show_turn($cmd,$bot);
		$this->cup_manager->map_picking_status = 2;
	}
}

/**
 * \brief Terminate picking before due time
 */
class Executor_Cup_Pick_Stop extends Executor_Cup
{
	function __construct(CachedCupManager $cup_manager)
	{
		parent::__construct($cup_manager,'stop','admin','stop',
			'Interrupt the current picking session and show the result');
	}
	
	function check_picking()
	{
		return $this->cup_manager->map_picking_status != 0;
	}
	
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		$bot->say($cmd->channel,"Map picking stopped",1024);
		$totmaps = array_merge($this->map_picker()->picks,$this->map_picker()->maps);
		$bot->say($cmd->channel,"Remaining maps: ".implode(', ',$totmaps),1024);
		$data->lists['player'] = array();
		$this->cup_manager->map_picker = null;
		$this->cup_manager->map_picking_status = 0;
	}
}

/**
 * \brief Show map picking turn
 */
class Executor_Cup_Pick_Turn extends Executor_Cup
{

	function __construct(CachedCupManager $cup_manager)
	{
		parent::__construct($cup_manager,'turn','player-admin','turn',
			'Show the current map picking turn');
		$this->on_picking = 2;
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		$this->map_pick_show_turn($cmd,$bot);
	}
}


/**
 * \brief Get a picking choice from the right player
 */
class Executor_Pick_Raw extends Executor_Cup
{

	function __construct(CachedCupManager $cup_manager, 
		$auth='player',$synopsis="",$description="",$irc_cmd='PRIVMSG')
	{
		parent::__construct($cup_manager,null,$auth,$synopsis,$description,$irc_cmd);
		$this->on_picking = 2;
	}
	
	
	function check(MelanoBotCommand $cmd,MelanoBot $bot,BotData $data)
	{
		return  $this->check_auth($cmd->from,$cmd->host,$bot,$data) &&
				$this->map_picker() && 
				$cmd->from == $this->map_picker()->current_player()->nick;
	}
	
	function install_on(BotCommandDispatcher $disp)
	{
		$disp->raw_executors []= $this;
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		if ( $cmd->cmd != null )
			$map = $cmd->cmd;
		else if ( !empty($cmd->params) )
			$map = $cmd->params[0];
		else
			return;
			
		if ( !$this->map_picker()->has_map($map) )
		{
			$bot->say($cmd->channel,"Map $map is not in the list",1024);
		}
		else
		{
			$this->map_picker()->choose($map);
			if ( count($this->map_picker()->maps) == 1 )
			{
				$this->map_picker()->picks []= $this->map_picker()->maps[0];
				$bot->say($cmd->channel,"Map picking ended",1024);
				$bot->say($cmd->channel,"Result: ".implode(', ',$this->map_picker()->picks),1024);
				$data->lists['player'] = array();
				$this->cup_manager->map_picker = null;
				$this->cup_manager->map_picking_status = 0;
				return;
			}
			$this->map_picker()->next_round();
		}
			
		$this->map_pick_show_turn($cmd,$bot);
	}
	
}

class Executor_Cup_AutoStartup extends Executor_Cup
{
	function __construct(CachedCupManager $cup_manager)
	{
		parent::__construct($cup_manager, null,null,"","","JOIN");
	}
	
	function check(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		return $cmd->from == $bot->nick;
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		$this->cup_manager->update_tournaments();
	}
}