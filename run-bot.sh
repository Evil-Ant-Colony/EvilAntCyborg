#!/bin/bash

bot_file=$1
if [ -z "$bot_file" ]
then
	bot_file="setup-bot.php"
elif ! ( echo $bot_file | grep -q "\.php$" )
then
	bot_file=$bot_file.php
fi

restart_file=.restart_$(basename $bot_file .php)

touch $restart_file
while [ -f $restart_file ]
do
    rm -f $restart_file
    php $bot_file
done 
