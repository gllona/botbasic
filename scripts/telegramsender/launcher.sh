#!/bin/sh
#
# cron-invoked BotBasic Telegram Splashes Sender paralell invoker

if [ $# -ne 2 ]; then
    echo "Usage: $0 <desired-throughput-splashes-per-minute> <telegram-webservice-request-time-rounded-up-msecs>"
    exit 1
fi

TP=$1   # desired throughput (splashes/min)
RT=$2   # request time rounded up (msecs)

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

NT=$(( (RT * TP) / (60 * 1000) ))   # number of threads (rounded down)
if [ $NT -gt 999 ]; then NT=999; fi
if [ $NT -lt 1   ]; then NT=1  ; fi
NTP=$NT; if [ $NT -lt 10 ]; then NTP="0$NTP"; fi; if [ $NT -lt 100 ]; then NTP="0$NTP"; fi

TT=0; while (true); do
    TTP=$TT; if [ $TT -lt 10 ]; then TTP="0$TTP"; fi; if [ $TT -lt 100 ]; then TTP="0$TTP"; fi
	/usr/bin/curl -k http://$SERVER/scripts/telegramsender/webscript.php?thread=$TTP\&threads=$NTP\&requestmsecs=$RT &
    TT=$(( TT+1 ))
    if [ $TT -eq $NT ]; then break; fi
done

exit 0
