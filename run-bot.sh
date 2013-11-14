#!/bin/bash

touch .restartbot
while [ -f .restartbot ]
do
    rm -f .restartbot 
    ./bot.php
done 
