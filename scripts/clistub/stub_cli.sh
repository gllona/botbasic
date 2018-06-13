#!/bin/bash

PHPFILENAME=stub_cli.php
BASEDIR=/home/gorka/telegram/panama_bot
PHPCODEDIR=$BASEDIR/httpdocs/scripts/clistub

exec php $PHPCODEDIR/$PHPFILENAME "$@"
