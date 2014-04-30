<?php

/**
 * This file has to be included if you want to create a bot instance the easy way
 * See setup-bot-simple-rcon.php
 */

require_once("irc/networks.php");
require_once("irc/executors/core.php");
require_once("irc/executors/message.php");
require_once("irc/executors/misc.php");
require_once('irc/executors/mediawiki.php');
require_once('irc/executors/yandex-translate.php');
require_once('irc/executors/fun.php');
require_once('irc/executors/webapi.php');
require_once('irc/executors/cup.php');
require_once('irc/executors/quakenet-auth.php');
require_once('irc/executors/ctcp.php');
require_once('rcon/rcon-communicator.php');
require_once('rcon/executors/rcon-extra.php');
require_once('rcon/executors/irc2rcon-status.php');
require_once('rcon/executors/irc2rcon-admin.php');

Logger::instance()->default_settings();

// Create a function that will create connections to the xonotic server
function create_communicator($driver, $channel, Rcon $rcon, $prefix, $esk)
{
	
	$rcon_comm = new Rcon_Communicator($channel,$rcon,$prefix);

	$driver->install_external($rcon_comm);
	$driver->install($rcon_comm);
	
	if ( $esk )
	{
		$rcon_comm->install(array(
			// Following commands require the ESK mod pack
			
			// better than Irc2Rcon_RawSayAdmin
			new Irc2Rcon_RawSay($rcon),
			new Irc2Rcon_UserEvent($rcon,"JOIN","has joined"), // will show all joins
			// new Irc2Rcon_UserJoin($rcon), // will show joins but not the bot's
			new Irc2Rcon_UserEvent($rcon,"PART","has parted"),
			new Irc2Rcon_UserEvent($rcon,"QUIT","has quit"),
			new Irc2Rcon_UserKicked($rcon),
			new Irc2Rcon_UserNick($rcon),
			
			// handle /me chats in the ESK mod pack
			new Rcon2Irc_SayAction(),
		));
	}
	else
		$rcon_comm->install(array(new Irc2Rcon_RawSayAdmin($rcon)));
	
	$rcon_comm->install(array(
	
	// "who" to list the connected players
	new Irc2Rcon_Who($rcon),
	// "maps pattern" to find maps matching pattern
	new Irc2Rcon_Maps($rcon),
	
// IRC - admin commands

	// (admin) "status" to view player, ip and such
	new Irc2Rcon_Status($rcon),
	// (admin) "rcon command" to execute arbitrary commands
	//new Irc2Rcon_Rcon($rcon),
	
	// (admin) list of single commands forwarded to rcon
	new Irc2Rcon_SingleCommand($rcon,"gotomap"),
	new Irc2Rcon_SingleCommand($rcon,"chmap"),
	new Irc2Rcon_SingleCommand($rcon,"endmatch"),
	new Irc2Rcon_SingleCommand($rcon,"restart"),
	new Irc2Rcon_SingleCommand($rcon,"mute"),
	new Irc2Rcon_SingleCommand($rcon,"unmute"),
	new Irc2Rcon_SingleCommand($rcon,"kick"),
	// (admin) vcall/vstop from IRC
	new Irc2Rcon_VCall($rcon),
	new Irc2Rcon_VStop($rcon),
	
	// (admin) ban management
	new Irc2Rcon_Command_Update($rcon,"ban","defer 1 banlist"),
	new Irc2Rcon_Command_Update($rcon,"unban","defer 1 banlist"),
	new Irc2Rcon_Command_Update($rcon,"kickban","defer 1 banlist"),
	// (admin) "banlist" to view active bans "banlist refresh" to update the banlist from the server
	new Irc2Rcon_Banlist($rcon),

// RCON - retrieve info
	// request updating g_maplist at the end of every match (needed by Irc2Rcon_Maps)
	new Rcon2Irc_SlowPolling(array("g_maplist")),
	// detect cvar changes (needed by Irc2Rcon_Maps)
	new Rcon2Irc_GetCvars(),
	// detect changes to the ban list (needed by Irc2Rcon_Banlist)
	new Rcon2Irc_UpdateBans(),
	
// RCON - notify admins
	// if a player prepends "!admin" to chat messages, admins will recieve a private message
	new Rcon2Irc_NotifyAdmin(),
	// if the server encounters an error, admins will recieve a private message
	new Rcon2Irc_HostError(),
	
// RCON -> IRC chat
	// plain chat
	new Rcon2Irc_Say(),
	
	// Show name changes
	new Rcon2Irc_Name(),
	
	// Show vote calls/results and so on
	new Rcon2Irc_Votes(),

	// Show score tables at the end of each match
	new Rcon2Irc_Score(),
	// Show scores inline at the end of each match
	//new Rcon2Irc_Score_Inline(),
	
	// Show match start notifications
	new Rcon2Irc_MatchStart(),

// RCON - filter some uniteresting output from rcon to get nicer logs
	new Rcon2Irc_Filter_BlahBlah(),
	));
	
	if ( get_param('geo_enable') )
	{
		$rcon_comm->install(array(
			new Rcon2Irc_Join("\00309+ join\xf: %name% \00302%country% \00304%map%\xf [\00304%players%\xf/\00304%max%\xf]"),
			new Rcon2Irc_Part("\00304- part\xf: %name% \00302%country% \00304%map%\xf [\00304%players%\xf/\00304%max%\xf]"),
		));
	}
	else
	{
		$rcon_comm->install(array(
			new Rcon2Irc_Join(),
			new Rcon2Irc_Part(),
		));
	}
	
	return $rcon_comm;
}

function get_param($name,$default=null)
{
	global $bot_config;
	return isset($bot_config[$name]) ? $bot_config[$name] : $default;
}



function get_dp_param($dparr,$name,$default=null)
{
	return isset($dparr[$name]) ? $dparr[$name] : $default;
}


function create_bot($params)
{
	global $bot_config;
	global $bot;
	global $driver;
	global $network_quakenet;
	$bot_config = $params;
	
	$bot = new MelanoBot(
		get_param('irc_server',$network_quakenet),
		get_param('irc_nick', 'UnnamedBot'),
		get_param('q_password'),
		get_param('irc_channels',array())
	);
	if ( !empty($bot_config['q_nick']) )
		$bot->auth_nick = $bot_config['q_nick'];
	if ( !empty($bot_config['irc_password']) )
		$bot->connection_password = $bot_config['irc_password'];
	$bot->auto_restart = true;
	
	$driver = new BotDriver($bot);
	foreach(get_param('irc_admins',array()) as $admin)
		$driver->data->add_to_list('owner',new IRC_User($admin));
	$driver->install_source(new Stdin_Data_Source);
	
	// Global commands available on every channel
	$disp_everywhere = new BotCommandDispatcher();
	$disp_everywhere->install(array(
	// owner commands
		new Executor_Reconnect(),
		new Executor_Quit(),
		new Executor_Restart(),
		new Executor_Join(),
		new Executor_Part(),
		new Executor_Nick(),
	// public commands
		new Executor_Help(),
		new Executor_License(), // Note: required to comply with the AGPL
	// Q auth
		new Executor_Q_SendWhois_Join(),
		new Executor_Q_GetWhois(),
	// CTCP
		new Executor_CTCP_Version(),
		new Executor_CTCP_PING(),
		new Executor_CTCP_Time(),
		new Executor_CTCP_Source(),
		new Executor_CTCP_ClientInfo(),
	));
	$driver->install($disp_everywhere);
	$driver->install_post_executor( new Post_Restart() );
	
	// bot admins are rcon admins
	$driver->data->grant_access['rcon-admin'] = array('admin');
	foreach(get_param('dp_servers',array()) as $dps)
	{
		create_communicator(
			$driver,
			get_dp_param($dps,'irc_channel'),
			new Rcon ( 
				get_dp_param($dps,'host',"127.0.0.1"),
				get_dp_param($dps,'port',26000),
				get_dp_param($dps,'rcon_password'),
				get_dp_param($dps,'rcon_secure',0),
				get_dp_param($dps,'log_dest_udp')
			),
			get_dp_param($dps,'prefix'),
			get_dp_param($dps,'esk_mod',false)
		);
	}
}

function start_bot()
{
	global $bot_config;
	global $driver;
	
	if ( get_param('geo_enable') )
	{
		require_once($bot_config['geo_inc']);
		RconPlayer::$geoip = geoip_open(get_param("/usr/share/GeoIP/GeoIP.dat"),GEOIP_STANDARD);
	}
	
	// start the bot
	$driver->run();
	
	if ( get_param('geo_enable') )
		geoip_close(RconPlayer::$geoip);
} 
