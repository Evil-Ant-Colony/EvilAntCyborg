<?php

require_once('cupmanager.php');

/**
 * \brief cup manager that keeps some off-line info
 */
class CachedCupManager extends CupManager
{
	public $cups, $current_cup;
	
	function CachedCupManager($api_key,$organization=null)
	{
		parent::__construct($api_key,$organization);
		$this->cups = $this->tournaments();
		$this->current_cup = empty($this->cups) ? null : $this->cups[0];
	}
	
	function update_tournaments()
	{
		$this->cups = $this->tournaments();
		if ( empty($this->cups) )
			$this->current_cup = null;
		else if ( $this->current_cup == null )
			$this->current_cup = $this->cups[0];
		else
		{
			foreach ( $this->cups as $cup )
			{
				if ( $cup->id == $this->current_cup->id )
				{
					$this->current_cup = $cup;
					return;
				}
			}
			$this->current_cup = $this->cups[0];
		}
	}
	
	
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
	
	function current_open_matches()
	{
		return $this->open_matches($this->current_cup->id);
	}
	
	
}

abstract class Executor_Cup extends CommandExecutor
{
	public $cup_manager;
	
	function Executor_Cup(CachedCupManager $cup_manager, 
						$name,$auth,$synopsis="",$description="",$irc_cmd='PRIVMSG')
	{
		parent::__construct($name,$auth,$synopsis,$description,$irc_cmd);
		$this->cup_manager = $cup_manager;
	}
	
	function check_cup(MelanoBotCommand $cmd, MelanoBot $bot)
	{
		if ( !$this->cup_manager->check_cup() )
		{
			$bot->say($cmd->channel,"No tournament is currently scheduled");
			return false;
		}
		return true;
	}
	
	function cup()
	{
		return $this->cup_manager->current_cup;
	}
}

abstract class Executor_Multi_Cup extends Executor_Cup
{
	private $multiple_inheritance;
	
	function Executor_Multi_Cup(CachedCupManager $cup_manager,$name,$auth,$executors,
		$synopsis="",$description="",$irc_cmd='PRIVMSG' )
	{
		parent::__construct($cup_manager,$name,$auth,$synopsis,$description,$irc_cmd);
		$this->multiple_inheritance = new Executor_Multi ($name,$executors);
	}
	
	
	function check_auth($nick,$host,BotDriver $driver)
	{
		return $this->multiple_inheritance->check_auth($nick,$host,$driver);
	}
	
	
	function check(MelanoBotCommand $cmd,MelanoBot $bot,BotDriver $driver)
	{
		return $this->multiple_inheritance->check($cmd,$bot,$driver);
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotDriver $driver)
	{
		return $this->multiple_inheritance->execute($cmd,$bot,$driver);
	}
	
	function executors()
	{
		return $this->multiple_inheritance->executors;
	}
	
	function help(MelanoBotCommand $cmd,MelanoBot $bot,BotDriver $driver)
	{
		return $this->multiple_inheritance->help($cmd,$bot,$driver);
	}
}

class Executor_Cup_Next extends Executor_Cup
{
	function Executor_Cup_Next(CachedCupManager $cup_manager)
	{
		parent::__construct($cup_manager,'next',null,'next [n]','Show the next (n) scheduled matches');
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotDriver $driver)
	{
		if ( $this->check_cup($cmd,$bot) )
		{
			$num = isset($cmd->params[0]) ? (int)$cmd->params[0] : 1;
			if ( $num > 5 && !$driver->user_in_list('admin',$cmd->from,$cmd->host) )
				$num = 5;
			$bot->say($cmd->channel,"Fetching...");
			$matches = $this->cup_manager->current_open_matches();
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
					sleep((1+$i)/10);
				}
			}
		}
	}
}

class Executor_Cup_Cups extends Executor_Cup
{
	function Executor_Cup_Cups(CachedCupManager $cup_manager)
	{
		parent::__construct($cup_manager,'cups','admin','cups','Show the available cups');
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotDriver $driver)
	{
		$this->cup_manager->update_tournaments();
		
		if ( empty($this->cup_manager->cups) )
			$bot->say($cmd->channel,"No cups available");
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
			$bot->say($cmd->channel,$text);
		}
	}
}

class Executor_Cup_Results extends Executor_Cup
{
	function Executor_Cup_Results(CachedCupManager $cup_manager)
	{
		parent::__construct($cup_manager,'results',null,'results',
			'Show a URL where you can view the cup details');
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotDriver $driver)
	{
		if ( $this->check_cup($cmd,$bot) )
			$bot->say($cmd->channel,$this->cup()->result_url());
	}
}


class Executor_Cup_CupReadonly extends Executor_Cup
{
	function Executor_Cup_CupReadonly(CachedCupManager $cup_manager)
	{
		parent::__construct($cup_manager,'cup',null,'cup',
			'Show the current cup name and ID');
	}
	
	function check(MelanoBotCommand $cmd,MelanoBot $bot,BotDriver $driver)
	{
		return count($cmd->params) == 0 || !$driver->user_in_list('admin',$cmd->from,$cmd->host) ;
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotDriver $driver)
	{
		if ( $this->check_cup($cmd,$bot) )
			$bot->say($cmd->channel,
				"Current cup: ".$this->cup()->name." - ".$this->cup()->id);
	}
}

class Executor_Cup_CupSelect extends Executor_Cup
{
	function Executor_Cup_CupSelect(CachedCupManager $cup_manager)
	{
		parent::__construct($cup_manager,'cup','admin',"cup [cup_name|cup_id]",
			'Change the current cup');
	}
	
	function check(MelanoBotCommand $cmd,MelanoBot $bot,BotDriver $driver)
	{
		return count($cmd->params) > 0 && $this->check_auth($cmd->from,$cmd->host,$driver) ;
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotDriver $driver)
	{
		$next = trim($cmd->param_string());
		$cup = null;
		foreach($this->cup_manager->cups as $c)
		{
			if ( $c->id == $next || $c->name == $next )
			{
				$cup = $c;
				$this->cup_manager->current_cup = $c;
				break;
			}
		}
		if ( $cup )
			$bot->say($cmd->channel,"Cup switched to: {$cup->name} - {$cup->id}");
		else
			$bot->say($cmd->channel,"Cup \"$next\" not found");
	}
	
}

class Executor_Cup_Cup extends Executor_Multi
{
	function Executor_Cup_Cup(CachedCupManager $cup_manager)
	{
		parent::__construct('cup',array(
			new Executor_Cup_CupSelect($cup_manager),
			new Executor_Cup_CupReadonly($cup_manager),
		));
	}
}


class Executor_Cup_DescriptionReadonly extends Executor_Cup
{
	function Executor_Cup_DescriptionReadonly(CachedCupManager $cup_manager)
	{
		parent::__construct($cup_manager,'description',null,'description',
			'Show the description of the current cup');
	}
	
	function check(MelanoBotCommand $cmd,MelanoBot $bot,BotDriver $driver)
	{
		return count($cmd->params) == 0 || !$driver->user_in_list('admin',$cmd->from,$cmd->host) ;
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotDriver $driver)
	{
		if ( $this->check_cup($cmd,$bot) )
		{
			$cup = $this->cup();
			$bot->say($cmd->channel,"Cup {$cup->name} ({$cup->id}): {$cup->description}");
		}
	}
}

class Executor_Cup_DescriptionSet extends Executor_Cup
{
	function Executor_Cup_DescriptionSet(CachedCupManager $cup_manager)
	{
		parent::__construct($cup_manager,'description','admin','description [new_description]',
			'Change the description for the current cup');
	}
	
	function check(MelanoBotCommand $cmd,MelanoBot $bot,BotDriver $driver)
	{
		return count($cmd->params) > 0 && $this->check_auth($cmd->from,$cmd->host,$driver) ;
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotDriver $driver)
	{
		if ( $this->check_cup($cmd,$bot) )
		{
			$cup = $this->cup();
			$cup->description = $cmd->param_string();
			$this->cup_manager->update_cup($cup);
			$bot->say($cmd->channel,"Cup {$cup->name} ({$cup->id}): {$cup->description}");
		}
	}
}


class Executor_Cup_Description extends Executor_Multi
{
	function Executor_Cup_Description(CachedCupManager $cup_manager)
	{
		parent::__construct('description',array(
			new Executor_Cup_DescriptionSet($cup_manager),
			new Executor_Cup_DescriptionReadonly($cup_manager),
		));
	}
}

class Executor_Cup_TimeReadonly extends Executor_Cup
{
	function Executor_Cup_TimeReadonly(CachedCupManager $cup_manager)
	{
		parent::__construct($cup_manager,'time',null,'time',
			'Display the scheduled start time for the current cup');
	}
	
	function check(MelanoBotCommand $cmd,MelanoBot $bot,BotDriver $driver)
	{
		return count($cmd->params) == 0 || !$driver->user_in_list('admin',$cmd->from,$cmd->host) ;
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotDriver $driver)
	{
		// noop
	}
}

class Executor_Cup_TimeSet extends Executor_Cup
{
	function Executor_Cup_TimeSet(CachedCupManager $cup_manager)
	{
		parent::__construct($cup_manager,'time','admin','time [new_time]',
			'Change the scheduled start time for the current cup');
	}
	
	function check(MelanoBotCommand $cmd,MelanoBot $bot,BotDriver $driver)
	{
		return count($cmd->params) > 0 && $this->check_auth($cmd->from,$cmd->host,$driver) ;
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotDriver $driver)
	{
		if ( $this->check_cup($cmd,$bot) )
		{
			$cup = $this->cup();
			
			$time = strtotime($cmd->param_string());
			if ( $time != null )
			{
				$cup->start_time = $time;
				$this->cup_manager->update_cup($cup);
			}
			else
				$bot->say($cmd->channel,"Invalid time format");
		}
	}
}

class Executor_Cup_Time extends Executor_Multi_Cup
{

	function Executor_Cup_Time(CachedCupManager $cup_manager)
	{
		parent::__construct($cup_manager,'time',null,array(
			new Executor_Cup_TimeSet($cup_manager),
			new Executor_Cup_TimeReadonly($cup_manager),
		));
	}
	
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotDriver $driver)
	{
		if ( $this->check_cup($cmd, $bot) )
		{
			parent::execute($cmd,$bot,$driver);
			
			$cup = $this->cup();
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
				$d_string .= "$d_min minutes";
				$bot->say($cmd->channel,"Cup will start in $d_string");
			}
		}
	}
}

class Executor_Cup_Maps extends Executor_Multi_Cup
{
	
	function Executor_Cup_Maps(CachedCupManager $cup_manager)
	{
		parent::__construct($cup_manager,'maps',null,array(
			new Executor_MiscListEdit('maps','admin'),
			new Executor_MiscListReadonly('maps',null)
		));
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotDriver $driver)
	{
		if ( $this->check_cup($cmd,$bot) )
		{
			$cup = $this->cup();
				
			foreach($this->executors() as $ex )
				if ( $ex->check($cmd,$bot,$driver) )
				{
					$ex->list = &$cup->maps;
					$ex->execute($cmd,$bot,$driver);
					break;
				}
			
			if ( count($cmd->params) > 0 && $driver->user_in_list('admin',$cmd->from,$cmd->host) )
			{			
				$this->cup_manager->update_cup($cup);
			}
		}
	}
	
}


class Executor_Cup_Start extends Executor_Cup
{
	function Executor_Cup_Start(CachedCupManager $cup_manager)
	{
		parent::__construct($cup_manager,'start','admin','start',
			'Start the cup');
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotDriver $driver)
	{
		if ( $this->check_cup($cmd,$bot) )
		{
			$cup = $this->cup();
			$cup->start();
			$cup->start_time = time();
			$bot->say($cmd->channel,"Cup started");
			$this->cup_manager->update_cup($cup);
		}
	}
}




class Executor_Cup_ScoreReadonly extends CommandExecutor
{
	function Executor_Cup_ScoreReadonly()
	{
		parent::__construct('score',null,'score match_id',
			'Show the scores for match_id');
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotDriver $driver)
	{
		// noop
	}
}

class Executor_Cup_ScoreSet extends CommandExecutor
{
	function Executor_Cup_ScoreSet()
	{
		parent::__construct('score','admin','score match_id score_1 score_2',
			'Appends the given scores to the score list in the given match');
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotDriver $driver)
	{
		// noop
	}
}

class Executor_Cup_Score extends Executor_Cup
{
	private $ro, $rw;
	
	function Executor_Cup_Score(CachedCupManager $cup_manager)
	{
		parent::__construct($cup_manager,'score',null);
		$this->ro = new Executor_Cup_ScoreReadonly();
		$this->rw = new Executor_Cup_ScoreSet();
		$this->reports_error = true;
	}
	
	function help(MelanoBotCommand $cmd,MelanoBot $bot,BotDriver $driver)
	{
		if ( $this->rw->check_auth($cmd->from,$cmd->host,$driver) )
		{
			$this->rw->help($cmd,$bot,$driver);
		}
		else
			$this->ro->help($cmd,$bot,$driver);
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotDriver $driver)
	{
		if ( $this->check_cup($cmd, $bot) )
		{
		
			if ( count($cmd->params) < 1 )
			{
				$bot->say($cmd->channel,"Which match?");
				return;
			}
			
			$cup = $this->cup();
			$match = $this->cup_manager->match($cup->id,$cmd->params[0]);
			if ( $match == null )
			{
				$bot->say($cmd->channel,"Match ".$cmd->params[0]." not found");
				return;
			}
			
			// matchID score1 score2
			if ( count($cmd->params) == 3 && $driver->user_in_list('admin',$cmd->from,$cmd->host)  ) 
			{
				$match->team1->add_score($cmd->params[1]);
				$match->team2->add_score($cmd->params[2]);
				$bot->say($cmd->channel,"Updated match ".$cmd->params[0].":");
				$this->cup_manager->update_match($cup,$match);
			}
			else
				$bot->say($cmd->channel,"Match {$match->id}:");
				
			$t1 = $match->team1();
			$t2 = $match->team2();
			$len=max(strlen($t1),strlen($t2));
			$bot->say($cmd->channel,str_pad($t1,$len).": ".$match->score1());
			$bot->say($cmd->channel,str_pad($t2,$len).": ".$match->score2()); 
		}
	}
}


class Executor_Cup_End extends Executor_Cup
{
	function Executor_Cup_End(CachedCupManager $cup_manager)
	{
		parent::__construct($cup_manager,'end','admin','end match_id',
			'End the given match and save score changes to challonge');
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotDriver $driver)
	{
		if ( $this->check_cup($cmd,$bot) )
		{
			$cup = $this->cup();
			$match = $this->cup_manager->match($cup->id,$cmd->params[0]);
			if ( $match == null )
			{
				$bot->say($cmd->channel,"Match ".$cmd->params[0]." not found");
				return;
			}
			
			$win = $match->winner();
			if ( $win == null )
			{
				$bot->say($cmd->channel,"Cannot end ".$cmd->params[0]." (no winner)");
				return;
			}
			
			$this->cup_manager->end_match($cup,$match);
			$bot->say($cmd->channel,"{$win->name} won match {$match->id} (".$match->team1()." vs ".$match->team2().")");
		}
	}
	
	
	function check(MelanoBotCommand $cmd,MelanoBot $bot,BotDriver $driver)
	{
		return count($cmd->params) == 1 && parent::check($cmd,$bot,$driver);
	}
	
	
}
