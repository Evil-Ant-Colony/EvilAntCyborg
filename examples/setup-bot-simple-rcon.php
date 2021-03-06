<?php

/**
 * This example shows how to set up a basic bot with rcon features
 */ 
 
require_once("examples/simple-setup.inc");
date_default_timezone_set("UTC");

create_bot(array(

// IRC Details
'irc_server'   => $network_quakenet, // Server or array of servers to connect to
'irc_nick'     => 'ExampleBot',      // Bot nickname and trigger for explicit commands
'irc_channels' => array(),           // Extra IRC channels the bot shall connect to
'irc_password' => null,              // IRC server/bouncer password

// Q AUTH
'q_nick'       => null,
'q_password'   => null,
'irc_admins'   => array(),           // List of admins registered to Q

// Darkplaces servers
'dp_servers'  => array(
	array(
		'irc_channel'    => '#channel', // IRC Channel
		'host'           => '127.0.0.1',// Server address
		'port'           => 26000,      // Server port
		'rcon_password'  => 'foo',      // Password
		'rcon_secure'    => 0,          // Secure protocol
		'prefix'         => 'xon',      // Chat prefix, set to null to disable
		'log_dest_udp'   => null,       // If the bot runs on a different host than DP, the address of such host
		'game'           => 'xonotic',  // Values: 'xonotic' 'xonotic-esk' 'nexuiz'
		'xonstats'       => null,       // Server ID for XonStats
		'stats_url'      => null,       // Full url for a server stats (if xonstats is set, http://stats.xonotic.org/server/<ID> will be used)
		'score_table'    => false,      // If true show a large score table, if false each team is shown in a single row
		'score_spectator'=> false,      // Whether to include spectators in the score list
		'admins'         => array(),    // List of IRC users (who aren't already bot admins) who will have access to admin features
	),
),

// GeoIP
'geo_enable' => false,                             // Whether to enable GeoIP for player join/part
'geo_inc'    => 'geoip-api-php-1.14/src/geoip.inc',// Include file for the PHP GeoIP API
'get_dat'    => "/usr/share/GeoIP/GeoIP.dat",      // GeoIP data file

));


start_bot();