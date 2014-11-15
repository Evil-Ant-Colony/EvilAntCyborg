<?php

/**
 * This example shows how to set up a bot connecting to multiple xonotic instances
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
// the bot must be registered to Q and have at least +k on the relevant channels

// Connect to a preset network:
$bot = new MelanoBot($network_quakenet,'ExampleBot','Auth password',array()); 

// Connect to a specific IRC server:
//$bot = new MelanoBot(new MelanoBotServer('localhost',6667),'ExampleBot','Auth password',array()); 
// If the bot has to be AUTHed as a different user
//$bot->auth_nick = 'SomeNick';
// If using a bouncer (or the server has a password)
//$bot->connection_password = 'ExampleBot/quakenet:bouncerpassword';

// Restart automatically if didn't quit cleanly
$bot->auto_restart = true;

$driver = new BotDriver($bot);

// Add a owner, a owner can perform any command and must be authed to Q
$driver->data->add_to_list('owner',new IRC_User('AuthedBotOwnerNick'));

// Allow reading commands from standard input
$driver->install_source(new Stdin_Data_Source);

// Global commands available on every channel
$disp_everywhere = new BotCommandDispatcher();
$disp_everywhere->install(array(
// owner commands
	new Executor_Reconnect(),
	new Executor_Quit(),
	new Executor_Restart(),
	new Executor_Join(),
	new Executor_Invite(),
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

// Create a function that will create connections to the xonotic server
function create_communicator($driver, $channel, Rcon $rcon,$prefix)
{
	static $server_number = 0;
	$rcon_comm = new Rcon_Communicator($channel,$rcon,$prefix,$server_number);
	$server_number++;
	
	$driver->install_external($rcon_comm);
	$driver->install($rcon_comm);
	
	$rcon_comm->install(array(
	// Following commands require the ESK mod pack
	/*
	// better than Irc2Rcon_RawSayAdmin
	new Irc2Rcon_RawSay($rcon),
	new Irc2Rcon_UserEvent($rcon,"JOIN","has joined"),
	new Irc2Rcon_UserEvent($rcon,"PART","has parted (%message%)"),
	new Irc2Rcon_UserEvent($rcon,"QUIT","has quit (%message%)"),
	new Irc2Rcon_UserEvent($rcon,"PART","has parted"),
	new Irc2Rcon_UserEvent($rcon,"QUIT","has quit"),
	new Irc2Rcon_UserKicked($rcon),
	new Irc2Rcon_UserNick($rcon),
	
	// handle /me chats in the ESK mod pack
	new Rcon2Irc_SayAction(),
	*/
	
// IRC - public commands

	// IRC->RCON using sv_adminnick and say (remove if using the ESK mod commands)
	new Irc2Rcon_RawSayAdmin($rcon), 
	
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
	new Rcon2Irc_SlowPolling(array("g_maplist","banlist")),
	// detect cvar changes (needed by Irc2Rcon_Maps)
	new Rcon2Irc_GetCvars(),
	// detect changes to the ban list (needed by Irc2Rcon_Banlist)
	new Rcon2Irc_UpdateBans(),
	// detect active mutators
	new Rcon2Irc_GetMutators(),
	
// RCON - notify admins
	// if a player prepends "!admin" to chat messages, admins will recieve a private message
	new Rcon2Irc_NotifyAdmin(),
	// if the server encounters an error, admins will recieve a private message
	new Rcon2Irc_HostError(),
	
// RCON -> IRC chat
	// display the irc channel when players say "!irc"
	new Rcon2Irc_ShowIRC(), 
	// plain chat
	new Rcon2Irc_Say(),
	
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
	// Show scores inline at the end of each match
	//new Rcon2Irc_Score_Inline(),
	
	// Show match start notifications
	new Rcon2Irc_MatchStart(),

// RCON - filter some uniteresting output from rcon to get nicer logs
	new Rcon2Irc_Filter_BlahBlah(),
	));
	
	return $rcon_comm;
}



// Attach rcon instance to #rcon.channel, using "server" as prefix
// chat to the server happens like this: server hello
// commands to the server like this: ExampleBot: server status
// you can omit the prefix and everything sent to the channel will be visible in the xonotic server
$rcon_comm_server = create_communicator(
	$driver,                               // Bot driver
	"#rcon.channel",                       // IRC Channel
	new Rcon ( "127.0.0.1", 26000, "foo"), // Rcon connection details, host, port, password, (rcon_secure=0), (local_address=host)
	"server"                               // Channel prefix, null or empty string to allow raw chats
);

// Create a connection to a xonotic server on a different machine
$rcon_comm_other = create_communicator ( $driver, "#rcon.channel",
	new Rcon ( "xonotic.server.address", 26000, "foo",0, "local.machine.address"),
	"other"
);

// Allow to invoke a command (or chat) on multiple servers
// The prefix "servers" will cause the following command to be directed to 
// the specified communicators
$driver->install(new Rcon_Multicast(
	"#rcon.channel",                           // IRC Channel
	array($rcon_comm_server,$rcon_comm_other), // Rcon communicators
	"servers"                                  // Prefix
));


// bot admins are rcon admins
$driver->data->grant_access['rcon-admin'] = array('admin');

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
