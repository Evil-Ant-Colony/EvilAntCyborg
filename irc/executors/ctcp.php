<?php
/**
 * \file
 * \author Mattia Basaglia
 * \copyright Copyright 2014 Mattia Basaglia
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

abstract class Executor_CTCP_Base extends RawCommandExecutor
{
	public $ctcp;
	
	
	function __construct($ctcp)
	{
		$this->ctcp = $ctcp;
	}
	
	function check(MelanoBotCommand $cmd,MelanoBot $bot,BotData $data)
	{
		return trim($cmd->cmd,"\01") == strtolower($this->ctcp) && 
			$cmd->from == $cmd->channel && $cmd->cmd[0] == "\01";
	}
	
	function response(MelanoBotCommand $cmd,MelanoBot $bot, $text)
	{
		$bot->command('NOTICE',"{$cmd->from} :\01".strtoupper($this->ctcp)." $text\01");
	}
}

class Executor_CTCP_Version extends Executor_CTCP_Base
{
	public $version_string;
	
	function __construct($version_string=null)
	{
		parent::__construct("version");
		if ( !$version_string )
		{
			$version_string = MelanoBot::$client_name.":".MelanoBot::$version.
				":PHP";
			@$ua = ini_get('user_agent');
			if ( $ua )
				$version_string .= " - $ua";
		}
		$this->version_string = $version_string;
	}
	
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		$this->response($cmd,$bot,$this->version_string);
	}
}

class Executor_CTCP_PING extends Executor_CTCP_Base
{	
	function __construct()
	{
		parent::__construct("ping");
	}
	
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		$this->response($cmd,$bot,trim($cmd->param_string(),"\01"));
	}
}

class Executor_CTCP_Time extends Executor_CTCP_Base
{	
	function __construct()
	{
		parent::__construct("time");
	}
	
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		$this->response($cmd,$bot,date('r'));
	}
}

class Executor_CTCP_Source extends Executor_CTCP_Base
{
	public $source;
	
	function __construct($source=null)
	{
		if ( !$source )
			$source = MelanoBot::$source_url;
		$this->source = $source;
		parent::__construct("source");
	}
	
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		$this->response($cmd,$bot,$this->source);
	}
}

class Executor_CTCP_ClientInfo  extends Executor_CTCP_Base
{
	
	function __construct()
	{
		parent::__construct("clientinfo");
	}
	
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		$list = array();
		foreach ( $data->driver->dispatchers as $disp )
		{
			foreach($disp->raw_executors as $ex)
				if ( $ex instanceof Executor_CTCP_Base )
					$list[] = strtoupper($ex->ctcp);
		}
		$list = array_unique($list);
		
		$this->response($cmd,$bot,implode(" ",$list));
	}
}


class Executor_CTCP_UserInfo extends Executor_CTCP_Base
{
	public $info;
	
	function __construct($info)
	{
		$this->info = $info;
		parent::__construct("userinfo");
	}
	
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		$this->response($cmd,$bot,$this->info);
	}
}

class Executor_CTCP_Custom extends Executor_CTCP_Base
{
	public $reply;
	
	function __construct($trigger,$reply)
	{
		parent::__construct($trigger);
		$this->reply = $reply;
	}
	
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		$this->response($cmd,$bot,$this->reply);
	}
}
