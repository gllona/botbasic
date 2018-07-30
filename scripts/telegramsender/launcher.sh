#!/bin/sh
#
# cron-invoked BotBasic Telegram Splashes Sender paralell invoker

if [ $# -lt 3 -o $# -gt 4 ]; then
    echo "Usage: $0 <local-server-name:local-server-port> <desired-throughput-splashes-per-minute> <telegram-webservice-request-time-rounded-up-msecs> [<max-splashes-to-send>]"
    exit 1
fi

HP=$1   # host:port
TP=$2   # desired throughput (splashes/min)
RT=$3   # request time rounded up (msecs)
HM=$4   # how many splashes to send (optional)

if [ "$HM" != "" ]; then HHM=maxtosend=$HM; else HHM=""; fi

NT=$(( (RT * TP) / (60 * 1000) ))   # number of threads (rounded down)
if [ $NT -gt 999 ]; then NT=999; fi
if [ $NT -lt 1   ]; then NT=1  ; fi
NTP=$NT; if [ $NT -lt 10 ]; then NTP="0$NTP"; fi; if [ $NT -lt 100 ]; then NTP="0$NTP"; fi

TT=0; while (true); do
    TTP=$TT; if [ $TT -lt 10 ]; then TTP="0$TTP"; fi; if [ $TT -lt 100 ]; then TTP="0$TTP"; fi
	/usr/bin/curl -k http://$HP/scripts/telegramsender/webscript.php?thread=$TTP\&threads=$NTP\&requestmsecs=$RT &
    TT=$(( TT+1 ))
    if [ $TT -eq $NT ]; then break; fi
done

exit 0
