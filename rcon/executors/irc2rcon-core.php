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

require_once("rcon/executors/rcon-abstract.php");



class Irc2Rcon_RawSayAdmin extends RawCommandExecutor
{
	public $say_command;
	public $rcon;
	
	function __construct(Rcon $rcon, $say_command='say ^7')
	{
		$this->say_command=$say_command;
		$this->rcon = $rcon;
	}
	
	
	function execute(MelanoBotCommand $cmd, MelanoBot $bot, BotData $data)
	{
		$rcon_data = $data->rcon["{$this->rcon->read}"];
		Rcon_Communicator::set_sv_adminnick($rcon_data,"[IRC] {$cmd->from}");
		$text = str_replace(array('\\','"'),array('\\\\','\"'),$cmd->param_string());
		if ( preg_match("{^\1ACTION ([^\1]*)\1$}", $text, $match) )
			$text = $match[1];
		$this->rcon->send($this->say_command." ".Color::irc2dp($text));
		Rcon_Communicator::restore_sv_adminnick($rcon_data);
	}
}