<?php

/**
 * This example shows how to set up a bot using most of the available features
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

date_default_timezone_set("UTC");

Logger::instance()->default_settings();
Logger::instance()->verbosity = 3;

// Note: to be able to retrieve auth information, the bot must be registered to Q
// The array contains the list of channels to connect to, you can omit channels 
// which are specified on BotCommandDispatcher instances
$bot = new MelanoBot($network_quakenet,'ExampleBot',null,
                        array('#example','#channel')); 

// If using a bouncer (or the server has a password)
//$bot->connection_password = 'ExampleBot/quakenet:bouncerpassword';

$bot->auto_restart = true;

$driver = new BotDriver($bot);

// note the nulls are for the authed user nick, currently authed users are only detected in QuakeNet
$driver->data->add_to_list('owner',new IRC_User('AuthedBotOwnerNick'));
$driver->data->add_to_list('admin',new IRC_User(null,'Admin1Nick'));
$driver->data->add_to_list('admin',new IRC_User(null,'Admin2Nick','example.com'));
$driver->add_to_list('blacklist',new IRC_User(null,'AnnoyingUser'));

// These messages are sent when a user joins the fun channel
$custom_greets = array(
	'AuthedBotOwnerNick' => 'welcomes his daddy',
);

// allow reading commands from standard input
$driver->install_source(new Stdin_Data_Source);

$driver->on_error = function (MelanoBotCommand $cmd, MelanoBot $bot, BotData $driver)
{
	$bot->say($cmd->channel,"Shut up ".$cmd->from);
};

$message_queue = new MessageQueue();

$driver->install_filter(array(
	/// Ignore blacklisted users
	new Filter_UserList('blacklist'),
	/// Allow sending messages from a channel (or a private message) to another
	new Filter_ChanHax('chanhax','admin'),
));

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
	new Executor_RawIRC('cmd'),
	new Executor_StdoutDump(),
// admin commands
	new Executor_UserList('admin','admin',array('owner'=>"But % is my daddy!")),
	new Executor_UserList('blacklist','admin',array('owner'=>"But % is my daddy!")),
// public commands
	new Executor_Help(),
	new Executor_License(), // Note: required to comply with the AGPL
// message handling
	new Executor_NotifyMessages($message_queue),
	new Executor_NotifyMessages($message_queue,'JOIN'),
	new Executor_Message($message_queue),
	new Executor_Tell($message_queue),
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
$driver->install_post_executor( new Post_Message_Store($message_queue) );
$driver->install_pre_executor( new Pre_Message_Restore($message_queue) );

// Some fun stuff to be displayed in a single channel
// To allow in multiple channel you can use array('#chan1', '#chan2')
// To allow in every channel, don't pass anything
$disp_fun = new BotCommandDispatcher("#funchan");
$disp_fun->install(array(
// admin
	new Executor_Action("please","admin"),
	new Executor_Morse(),
// public commands
	new Executor_Echo("say"),
	new Executor_Echo("slap","slaps"),
	/// \note you should set the user agent if using this
	new Executor_Wiki("describe","Wikipedia","http://en.wikipedia.org/w/api.php"),
	new Executor_GoogleImages(),
	new Executor_Youtube(),
	new Executor_ReverseText(),
	new Executor_Dictionary(),
	new Executor_ChuckNorris(),
// non-PRIVMSG
	new Executor_RespondKick(),
	new Executor_GreetingSelf("Hello!!!"),
	new Executor_GreetingUsers($custom_greets),
// filters
	new Filter_UserArray(array("Q")), // to avoid loops with "what?"
// Raw
	new Raw_Youtube(),
	new Raw_Echo("Fun!","?!",'admin'),
	new Raw_Question(),
	new Raw_What(), /// KEEP AS LAST
));

// Set up challonge cup management
$driver->data->grant_access['player-admin'] = array('player','admin');
$cup_manager = new CachedCupManager('your challonge API key','challonge organization (optional)');
$disp_cup = new BotCommandDispatcher("#cup");

$disp_cup->install(array(
// cup commands (admin)
	new Executor_Cup_Cups($cup_manager),
	new Executor_Cup_Start($cup_manager),
	new Executor_Cup_End($cup_manager),
	new Executor_Cup_Pick_Setup($cup_manager),
	new Executor_Cup_Pick_Stop($cup_manager),
	new Executor_Cup_Pick_Begin($cup_manager),
	new Executor_Cup_Pick_Nick($cup_manager),
	new Executor_Cup_Pick_Pick($cup_manager),
// cup commands (admin+anyone)
	new Executor_Cup_Cup($cup_manager),
	new Executor_Cup_Description($cup_manager),
	new Executor_Cup_Time($cup_manager),
	new Executor_Cup_Maps($cup_manager),
	new Executor_Cup_Score($cup_manager),
// cup commands (anyone)
	new Executor_Cup_Next($cup_manager),
	new Executor_Cup_Results($cup_manager),
// cup commands (selected players)
	new Executor_Pick_Raw($cup_manager),
// non-PRIVMSG
	new Executor_GreetingSelf("Cup bot is ready!"),
	new Executor_Cup_AutoStartup($cup_manager),
));

// Set up a rcon connection to a Xonotic server
function rcon_comm($driver, Rcon $rcon,$channel,$prefix)
{
	static $server_number = 0;
	$rcon_comm = new Rcon_Communicator($channel,$rcon,$prefix,$server_number);
	$server_number++;
	
	$driver->install_external($rcon_comm);
	$rcon_comm->install(array(
	// Following commands require the ESK mod pack
	/*
	// better than Irc2Rcon_RawSayAdmin
	new Irc2Rcon_RawSay($rcon_test),
	new Irc2Rcon_UserEvent($rcon,"JOIN","has joined"),
	new Irc2Rcon_UserEvent($rcon,"PART","has parted (%message%)"),
	new Irc2Rcon_UserEvent($rcon,"QUIT","has quit (%message%)"),
	new Irc2Rcon_UserKicked($rcon_test),
	new Irc2Rcon_UserNick($rcon_test),
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
	new Rcon2Irc_SlowPolling(array("g_maplist")),
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
	// Show scores inline at the end of each match
	//new Rcon2Irc_Score_Inline(),
	
	// Show match start notifications
	new Rcon2Irc_MatchStart(),

// RCON - filter some uniteresting output from rcon to get nicer logs
	new Rcon2Irc_Filter_BlahBlah(),
	));
	
	return $rcon_comm;
}

// bot admins are rcon admins
$driver->data->grant_access['rcon-admin'] = array('admin');

// Rcon connection details, host, port, password, (rcon_secure=0), (local_address=host)
$rcon_test = new Rcon ( "127.0.0.1", 26000, "foo");
// attach rcon instance to #rcon.channel, using test as prefix
// chat to the server happens like this: test hello
// commands to the server like this: (BotNick): test status
// you can omit the prefix and everything sent to the channel will be visible in the xonotic server
$rcon_test_comm = rcon_comm($driver,$rcon_test,"#rcon.channel","server");


// Install dispatchers

$driver->install_post_executor( new Post_Restart() );

$driver->install(array(
	$disp_everywhere,
	$disp_cup,
	$rcon_test_comm,
	$disp_fun, /// KEEP AS LAST
));

// start the bot
$driver->run();


/*
// If you want to see RCON player countries:

// Change Join/Part messages to include %country%

// Something like this instead of just $driver->run()
require_once("geoip-api-php-1.14/src/geoip.inc");
RconPlayer::$geoip = geoip_open("/usr/share/GeoIP/GeoIP.dat",GEOIP_STANDARD);
$driver->run();
geoip_close(RconPlayer::$geoip);
*/