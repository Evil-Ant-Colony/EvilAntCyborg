<?php


const ANYONE= 0;
const ADMIN = 1;
const OWNER = 2;
$help = array(
    'help'=>array(
        'auth'=>ANYONE,
        'synopsis'=>'help [command]',
        'desc'=>'Guess what this does...',
    ),
    'message'=>array(
        'auth'=>ANYONE,
        'synopsis'=>'message',
        'desc'=>'Read the first stored message the bot has for you',
    ),
    'tell'=>array(
        'auth'=>ANYONE,
        'synopsis'=>'tell User Message...',
        'desc'=>'Store a message for User',
    ),
    'do'=>array(
        'auth'=>ADMIN,
        'synopsis'=>'do Action...',
        'desc'=>'Make the bot perform a chat action (Roleplaying)',
    ),
    'noblacklist'=>array(
        'auth'=>ADMIN,
        'synopsis'=>'noblacklist User',
        'desc'=>'Remove user from blacklist',
    ),
    'blacklist'=>array(
        'auth'=>ADMIN,
        'synopsis'=>'blacklist User',
        'desc'=>'Add user to blacklist',
    ),
    'nowhitelist'=>array(
        'auth'=>ADMIN,
        'synopsis'=>'nowhitelist User',
        'desc'=>'Remove user from whitelist',
    ),
    'whitelist'=>array(
        'auth'=>ADMIN,
        'synopsis'=>'blacklist User',
        'desc'=>'Add user to whitelist',
    ),
    'cmd'=>array(
        'auth'=>OWNER,
        'synopsis'=>'cmd IRC_CMD [irc_options...]',
        'desc'=>'Execute an arbitrary IRC command',
    ),
    'bye'=> array(
        'auth'=>ANYONE,
        'synopsis'=>'(automatic)',
        'desc'=>'Say goodbye to parting/quitting users (automatic)',
    ),
    'hello'=>array(
        'auth'=>ANYONE,
        'synopsis'=>'hello',
        'desc'=>'Say hello to the user sending the command',
    ),
    'greet'=>array(
        'auth'=>ANYONE,
        'synopsis'=>'(automatic)',
        'desc'=>'Say hello to users joining the channel',
    ),
    'slap'=>array(
        'auth'=>ANYONE,
        'synopsis'=>'slap Text...',
        'desc'=>'Make the bot slap someone',
    ),
    'say'=>array(
        'auth'=>ANYONE,
        'synopsis'=>'say Text...',
        'desc'=>'Make the bot say something',
    ),
    'nick'=>array(
        'auth'=>OWNER,
        'synopsis'=>'nick Nickname',
        'desc'=>'Change nickname',
    ),
    'join'=>array(
        'auth'=>OWNER,
        'synopsis'=>'join #Channel',
        'desc'=>'Make the bot join a channel',
    ),
    'part'=>array(
        'auth'=>OWNER,
        'synopsis'=>'part [#Channel]',
        'desc'=>'Make the bot part the current channel or the one specified',
    ),
    'restart'=>array(
        'auth'=>OWNER,
        'synopsis'=>'restart',
        'desc'=>'Restart the bot (quit and rejoin)',
    ),
    'quit'=>array(
        'auth'=>OWNER,
        'synopsis'=>'quit',
        'desc'=>'Shut down the bot',
    ),
    'shut'=>array(
        'auth'=>OWNER,
        'synopsis'=>'shut [up|down|actually anything...]',
        'desc'=>'Shut down the bot',
    ),
    'ragequit'=>array(
        'auth'=>OWNER,
        'synopsis'=>'ragequit',
        'desc'=>'Shut down the bot',
    ),
    'chanhax'=>array(
        'auth'=>OWNER,
        'synopsis'=>'[command] chanhax #Channel',
        'desc'=>'Execute the command on the given channel',
    ),
);



