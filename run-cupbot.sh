#!/bin/bash

restart_file=.restart_cup_bot
touch $restart_file
while [ -f $restart_file ]
do
    rm -f $restart_file
    php cupbot.php
done 
