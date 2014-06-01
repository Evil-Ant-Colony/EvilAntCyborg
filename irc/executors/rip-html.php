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

require_once("irc/bot-driver.php");


abstract class Executor_RipHtmlBase extends CommandExecutor
{
	function __construct($trigger,$auth,$synopsis="",$description="",$irc_cmd='PRIVMSG')
	{
		parent::__construct($trigger,$auth,$synopsis,$description,$irc_cmd);
	}
	
	
	protected function get_contents($url, $xpath)
	{
		$doc = new DOMDocument();
		$doc->strictErrorChecking = false;
		$doc->recover=true;
		if ( !$doc->loadHTMLFile($url) )
		{
			Logger::log("sys","!","Error loading $url");
			return array();
		}
		
		$dom_xpath = new DOMXpath($doc);
		$nodes = $dom_xpath->query($xpath);
		
		$contents = array();
		if ($nodes) 
		{
			foreach ($nodes as $node) 
				$contents []= $node->textContent;
		}
		
		return $contents;
	}
}
	
	
class Executor_RipHtmlSimple extends Executor_RipHtmlBase
{
	public $url, $xpath;
	function __construct($url, $xpath,$trigger,$auth,$description="")
	{
		parent::__construct($trigger,$auth,"$trigger",$description);
		$this->url = $url;
		$this->xpath = $xpath;
	}
	
	function url(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		return $this->url;
	}
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
	{
		$contents = $this->get_contents($this->url, $this->xpath);
		print_r($contents);
		if ( count($contents) > 0 )
		{
			$bot->say($cmd->channel,implode(" ",$contents));
		}
	}
	
} 
