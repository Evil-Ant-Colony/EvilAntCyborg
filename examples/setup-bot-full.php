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

$bot = new MelanoBot($network_quakenet,'ExampleBot',null,
                        array('#example','#channel')); 

$bot->auto_restart = true;

$driver = new BotDriver($bot);

// note the nulls are for the authed user nick, currently authed users are only detected in QuakeNet
$driver->data->add_to_list('owner',new IRC_User('AuthedBotOwnerNick'));
$driver->data->add_to_list('admin',new IRC_User(null,'Admin1Nick'));
$driver->data->add_to_list('admin',new IRC_User(null,'Admin2Nick','example.com'));
$driver->add_to_list('blacklist',new IRC_User(null,'AnnoyingUser'));

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
	new Filter_UserList('blacklist'),
	new Filter_ChanHax('chanhax','admin')
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

// Some fun stuff to be displayed in a single channel
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
	$rcon_comm = new Rcon_Communicator($channel,$rcon,$prefix);

	$driver->install_external($rcon_comm);
	$rcon_comm->install(array(
		// Following commands require the ESK mod pack
		/*
		new Irc2Rcon_RawSay($rcon),
		new Irc2Rcon_UserEvent($rcon,"JOIN","has joined"),
		new Irc2Rcon_UserEvent($rcon,"PART","has parted"),
		new Irc2Rcon_UserEvent($rcon,"QUIT","has quit"),
		new Irc2Rcon_UserKicked($rcon),
		new Irc2Rcon_UserNick($rcon),
		*/
		new Irc2Rcon_RawSayAdmin($rcon),
		
		new Irc2Rcon_Who($rcon),
		new Irc2Rcon_Status($rcon),
		new Irc2Rcon_Maps($rcon),
		new Irc2Rcon_Rcon($rcon), /// \warning may be dangerous!
		new Irc2Rcon_SingleCommand($rcon,"gotomap"),
		new Irc2Rcon_SingleCommand($rcon,"chmap"),
		new Irc2Rcon_SingleCommand($rcon,"endmatch"),
		new Irc2Rcon_SingleCommand($rcon,"restart"),
		new Irc2Rcon_SingleCommand($rcon,"mute"),
		new Irc2Rcon_SingleCommand($rcon,"unmute"),
		new Irc2Rcon_SingleCommand($rcon,"kick"),
		new Irc2Rcon_VCall($rcon),
		new Irc2Rcon_VStop($rcon),
		new Irc2Rcon_Command_Update($rcon,"ban","defer 1 banlist"),
		new Irc2Rcon_Command_Update($rcon,"unban","defer 1 banlist"),
		new Irc2Rcon_Command_Update($rcon,"kickban","defer 1 banlist"),
		new Irc2Rcon_Banlist($rcon),

		new Rcon2Irc_SlowPolling(array("g_maplist")),
		new Rcon2Irc_GetCvars(),
		new Rcon2Irc_UpdateBans(),
		
		new Rcon2Irc_NotifyAdmin(),
		new Rcon2Irc_HostError(),
		new Rcon2Irc_Say(),
		new Rcon2Irc_SayAction(),
		
		new Rcon2Irc_Join(),
		new Rcon2Irc_Part(),
		new Rcon2Irc_Name(),
		
		new Rcon2Irc_Score(),
		new Rcon2Irc_Votes(),
		new Rcon2Irc_MatchStart(),
		
		new Rcon2Irc_Filter_BlahBlah(),
	));
	
	return $rcon_comm;
}

$driver->data->grant_access['rcon-admin'] = array('admin');
$rcon_test = new Rcon ( "127.0.0.1", 26000, "foo");
// attach rcon instance to #rcon.channel, using test as prefix
// chat to the server happens like this: test hello
// commands to the server like this: (BotNick): test status
// you can omit the prefix and everything sent to the channel will be visible in the xonotic server
$rcon_test_comm = rcon_comm($driver,$rcon_test,"#rcon.channel","server");

$driver->install_post_executor( new Post_Restart() );

$driver->install(array(
	$disp_everywhere,
	$disp_cup,
	$rcon_test_comm,
	$disp_fun, /// KEEP AS LAST
));

// start the bot
$driver->run();
