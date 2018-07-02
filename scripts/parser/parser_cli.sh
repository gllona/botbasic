#!/bin/bash

PHPFILENAME=BotBasicParser.php
BASEDIR=/home/botbasic
PHPCODEDIR=$BASEDIR/httpdocs/botbasic

exec php $PHPCODEDIR/$PHPFILENAME "$@"
