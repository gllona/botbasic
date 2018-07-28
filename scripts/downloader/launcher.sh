#!/bin/sh
#
# cron-invoked BotBasic Resources Downloader paralell invoker

if [ $# -ne 3 ]; then
    echo "Usage: $0 <local-server-name:local-server-port> <desired-throughput-splashes-per-minute> <telegram-webservice-request-time-rounded-up-msecs>"
    exit 1
fi

CHATMEDIUMTELEGRAMTYPE=111

HP=$1   # host:port
TP=$2   # desired throughput (splashes/min)
RT=$3   # request time rounded up (msecs)

NT=$(( (RT * TP) / (60 * 1000) ))   # number of threads (rounded down)
if [ $NT -gt 999 ]; then NT=999; fi
if [ $NT -lt 1   ]; then NT=1  ; fi
NTP=$NT; if [ $NT -lt 10 ]; then NTP="0$NTP"; fi; if [ $NT -lt 100 ]; then NTP="0$NTP"; fi

TT=0; while (true); do
    TTP=$TT; if [ $TT -lt 10 ]; then TTP="0$TTP"; fi; if [ $TT -lt 100 ]; then TTP="0$TTP"; fi
	#/usr/bin/curl -k http://$HP/scripts/downloader/webscript.php?thread=$TTP\&threads=$NTP\&requestmsecs=$RT &
	#/usr/bin/curl -k http://$HP/scripts/downloader/webscript.php?thread=$TTP\&threads=$NTP\&requestmsecs=$RT\&chatmediumid=$CHATMEDIUMTELEGRAMTYPE &
	/usr/bin/curl -k http://$HP/scripts/downloader/webscript.php?chatmediumid=$CHATMEDIUMTELEGRAMTYPE &
    TT=$(( TT+1 ))
    if [ $TT -eq $NT ]; then break; fi
done

exit 0
