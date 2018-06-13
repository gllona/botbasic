#!/bin/sh
#
# cron-invoked BotBasic Resources Downloader paralell invoker
#
# current tstime duration: SCRIPT_EXECUTION_TIME = 50 secs 
#   TODO: measure and recalc; tune BOTBASIC_DOWNLOADDAEMON_TELEGRAM_HOW_MANY_TO_DOWNLOAD for previous to be 60 secs or a bit less
#   TODO: check telegramsender/launcher.sh for auto calc of number of concurrent processes
# values from bbdefines.php:
#   BOTBASIC_DOWNLOADDAEMON_TELEGRAM_HOW_MANY_TO_DOWNLOAD = 15
# calc:
#   THROUGHTPUT = NUMTHREADS * BOTBASIC_DOWNLOADDAEMON_TELEGRAM_HOW_MANY_TO_DOWNLOAD (splashes/min)
#   THROUGHTPUT = 150 (now)

HN=`hostname`
case $HN in
hp-envy)
	SERVER=panama_bot.local:80
	;;
*)
	SERVER=${HN}.local:80
	;;
esac
if [ "$SERVER" == "" ]; then
	echo "$0: edit script and include hostname-to-localservername mapping"
	exit 1
fi

CHATMEDIUMTELEGRAMTYPE=111
NUMTHREADS=10   # same as below please
for i in 1 2 3 4 5 6 7 8 9 10
do
	/usr/bin/curl -k http://$SERVER/scripts/downloader/webscript.php?chatmediumid=$CHATMEDIUMTELEGRAMTYPE &
done
