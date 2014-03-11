<?php

require_once("irc/bot-driver.php");
require_once("misc/logger.php");

class MessageQueue
{
	public $messages = array();
	
	function count_messages($nick)
	{
		return !isset($this->messages[$nick]) ? 0 : count($this->messages[$nick]["queue"]);
	}
	
	function pop_message($nick)
	{
		$this->messages[$nick]["notified"] = true;
		return array_shift($this->messages[$nick]["queue"]);
	}
	
	function send($from,$to,$text)
	{
		if ( !isset($this->messages[$to]) )
			$messages[$to] = array("notified"=>false,"queue"=>array());
		$this->messages[$to]["notified"] = false;
		$this->messages[$to]["queue"][]= array("from"=>$from,"msg"=>$text);
	}
	
	function needs_notification($nick)
	{
		return $this->count_messages($nick) > 0 && !$this->messages[$nick]["notified"];
	}
}

class Executor_Message extends CommandExecutor
{
	public $queue;
	function Executor_Message(MessageQueue $queue)
	{
		parent::__construct("message",null,'message','Read the first stored message the bot has for you');
		$this->queue = $queue;
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		if ( $this->queue->count_messages($cmd->from) > 0 )
		{
			$msg = $this->queue->pop_message($cmd->from);
			$bot->say($cmd->channel,"<{$msg['from']}> {$msg['msg']}");
			$bot->say($cmd->channel,"You have other ". $this->queue->count_messages($cmd->from)." messages");
		}
		else
			$bot->say($cmd->channel, "You don't have any message");
	}
	
}


class Executor_Tell extends CommandExecutor
{
	public $queue;
	function Executor_Tell(MessageQueue $queue)
	{
		parent::__construct("tell",null,'tell User Message...','Store a message for User');
		$this->queue = $queue;
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
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
				$this->queue->send($cmd->from,$who,$text);
				Logger::log($msg,"!","Stored a message from {$cmd->from} to $who");
				$bot->say($cmd->channel, "Will do!");
			}
		}
	}
	
}
 

class Executor_NotifyMessages extends CommandExecutor
{
	public $queue;
	function Executor_NotifyMessages(MessageQueue $queue,$irc_cmd='PRIVMSG')
	{
		parent::__construct("hello",null,'hello','List messages');
		$this->queue = $queue;
		$this->irc_cmd = $irc_cmd;
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		if ( $cmd->from != $bot->nick )
		{
			if ( $cmd->irc_cmd == 'PRIVMSG' )
				$bot->say($cmd->channel,"Hello $cmd->from!!!");
			if ( $this->queue->needs_notification($cmd->from) )
			{
				$this->queue->messages[$cmd->from]["notified"] = true;
				$bot->say($cmd->from,"You have ".$this->queue->count_messages($cmd->from)." messages");
			}
			
		}
	}
}