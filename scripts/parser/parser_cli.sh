#!/bin/bash

PHPFILENAME=BotBasicParser.php
BASEDIR=/home/gorka/telegram/panama_bot
PHPCODEDIR=$BASEDIR/httpdocs/botbasic

exec php $PHPCODEDIR/$PHPFILENAME "$@"
