#!/bin/bash

PHPFILENAME=stub_cli.php
BASEDIR=/home/botbasic
PHPCODEDIR=$BASEDIR/httpdocs/scripts/clistub

exec php $PHPCODEDIR/$PHPFILENAME "$@"
