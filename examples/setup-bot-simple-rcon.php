<?php

/**
 * This example shows how to set up a bot connecting to a single xonotic instance on a single channel
 */ 

require_once("irc/networks.php");
require_once("irc/executors/core.php");
require_once('irc/executors/quakenet-auth.php');
require_once('irc/executors/ctcp.php');
require_once('rcon/rcon-communicator.php');
require_once('rcon/executors/rcon-extra.php');
require_once('rcon/executors/irc2rcon-status.php');
require_once('rcon/executors/irc2rcon-admin.php');

date_default_timezone_set("UTC");

Logger::instance()->default_settings();

// Note: to be able to retrieve auth information, the bot must be registered to Q
$bot = new MelanoBot($network_quakenet,'ExampleBot','Auth password',array()); 

// If using a bouncer (or the server has a password)
//$bot->connection_password = 'ExampleBot/quakenet:bouncerpassword';

// Restart automatically if didn't quit cleanly
$bot->auto_restart = true;

$driver = new BotDriver($bot);

$driver->data->add_to_list('owner',new IRC_User('AuthedBotOwnerNick'));
$driver->data->add_to_list('admin',new IRC_User('AuthedAdmin1Nick'));
$driver->data->add_to_list('admin',new IRC_User('AuthedAdmin2Nick'));

// Allow reading commands from standard input
$driver->install_source(new Stdin_Data_Source);


// Global commands available on every channel
$disp_everywhere = new BotCommandDispatcher();
$disp_everywhere->install(array(
// owner commands
	new Executor_Reconnect(),
	new Executor_Server(),
	new Executor_Quit(),
	new Executor_Restart(),
	new Executor_Join(),
	new Executor_Part(),
	new Executor_Nick(),
	new Executor_StdoutDump(),
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

// Rcon connection details, host, port, password
// Note: as of now, it only supports rcon_secure 0
$rcon_test = new Rcon ( "127.0.0.1", 26000, "foo");
// Attach rcon instance to #rcon.channel, using server as prefix
// chat to the server happens like this: server hello
// commands to the server like this: ExampleBot: server status
// you can omit the prefix and everything sent to the channel will be visible in the xonotic server
$rcon_test_comm = new Rcon_Communicator("#rcon.channel",$rcon_test,"server");

$driver->install_external($rcon_test_comm);
$rcon_test_comm->install(array(
	// Following commands require the ESK mod pack
	/*
	// better than Irc2Rcon_RawSayAdmin
	new Irc2Rcon_RawSay($rcon_test),
	new Irc2Rcon_UserEvent($rcon_test,"JOIN","has joined"),
	new Irc2Rcon_UserEvent($rcon_test,"PART","has parted"),
	new Irc2Rcon_UserEvent($rcon_test,"QUIT","has quit"),
	new Irc2Rcon_UserKicked($rcon_test),
	new Irc2Rcon_UserNick($rcon_test),
	*/
	
// IRC - public commands

	// IRC->RCON using sv_adminnick and say
	new Irc2Rcon_RawSayAdmin($rcon_test), 
	
	// "who" to list the connected players
	new Irc2Rcon_Who($rcon_test),
	// "maps pattern" to find maps matching pattern
	new Irc2Rcon_Maps($rcon_test),
	
// IRC - admin commands

	// (admin) "status" to view player, ip and such
	new Irc2Rcon_Status($rcon_test),
	// (admin) "rcon command" to execute arbitrary commands
	new Irc2Rcon_Rcon($rcon_test),
	// (admin) list of single commands forwarded to rcon
	new Irc2Rcon_SingleCommand($rcon_test,"gotomap"),
	new Irc2Rcon_SingleCommand($rcon_test,"chmap"),
	new Irc2Rcon_SingleCommand($rcon_test,"endmatch"),
	new Irc2Rcon_SingleCommand($rcon_test,"restart"),
	new Irc2Rcon_SingleCommand($rcon_test,"mute"),
	new Irc2Rcon_SingleCommand($rcon_test,"unmute"),
	new Irc2Rcon_SingleCommand($rcon_test,"kick"),
	// (admin) vcall/vstop from IRC
	new Irc2Rcon_VCall($rcon_test),
	new Irc2Rcon_VStop($rcon_test),
	
	// (admin) ban management
	new Irc2Rcon_Command_Update($rcon_test,"ban","defer 1 banlist"),
	new Irc2Rcon_Command_Update($rcon_test,"unban","defer 1 banlist"),
	new Irc2Rcon_Command_Update($rcon_test,"kickban","defer 1 banlist"),
	// (admin) "banlist" to view active bans "banlist refresh" to update the banlist from the server
	new Irc2Rcon_Banlist($rcon_test),

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
	// handle /me chats in the ESK mod pack
	// new Rcon2Irc_SayAction(),
	
	// Show joins
	new Rcon2Irc_Join(),
	// If you have GeoIP enabled and want to see the country, use this instead
	//new Rcon2Irc_Join("\00309+ join\xf: %name% \00302%country% \00304%map%\xf [\00304%players%\xf/\00304%max%\xf]"),
		
	// Show parts
	new Rcon2Irc_Part(),
	// If you have GeoIP enabled and want to see the country, use this instead
	//new Rcon2Irc_Part("\00304- part\xf: %name% \00302%country% \00304%map%\xf [\00304%players%\xf/\00304%max%\xf]"),
	
	// Show name changes
	new Rcon2Irc_Name(),
	
	// Show vote calls/results and so on
	new Rcon2Irc_Votes(),

	// Show score tables at the end of each match
	new Rcon2Irc_Score(),
	
	// Show match start notifications
	new Rcon2Irc_MatchStart(),

// RCON - filter some uniteresting output from rcon to get nicer logs
	new Rcon2Irc_Filter_BlahBlah(),
));

// bot admins are rcon admins
$driver->data->grant_access['rcon-admin'] = array('admin');

$driver->install_post_executor( new Post_Restart() );

$driver->install(array(
	$disp_everywhere,
	$rcon_test_comm,
));

// start the bot
$driver->run();


/*
// If you want to see player countries:

// Change Join/Part messages to include %country%

// Something like this instead of just $driver->run()
require_once("geoip-api-php-1.14/src/geoip.inc");
RconPlayer::$geoip = geoip_open("/usr/share/GeoIP/GeoIP.dat",GEOIP_STANDARD);
$driver->run();
geoip_close(RconPlayer::$geoip);
*/