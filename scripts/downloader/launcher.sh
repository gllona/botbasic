#!/bin/sh
#
# cron-invoked BotBasic Resources Downloader paralell invoker

if [ $# -lt 3 -o $# -gt 4 ]; then
    echo "Usage: $0 <local-server-name:local-server-port> <number-of-threads> <wait-between-downloads-msecs> [<max-resources-to-download>]"
    exit 1
fi

HP=$1   # host:port
NT=$2   # desired throughput (splashes/min)
WT=$3   # wait time between downloads (msecs)
HM=$4   # how many resources to download (optional)

if [ "$HM" != "" ]; then HHM=maxtodownload=$HM; else HHM=""; fi

CHATMEDIUMTELEGRAMTYPE=111

if [ $NT -gt 999 ]; then NT=999; fi
if [ $NT -lt 1   ]; then NT=1  ; fi
NTP=$NT; if [ $NT -lt 10 ]; then NTP="0$NTP"; fi; if [ $NT -lt 100 ]; then NTP="0$NTP"; fi

TT=0; while (true); do
    TTP=$TT; if [ $TT -lt 10 ]; then TTP="0$TTP"; fi; if [ $TT -lt 100 ]; then TTP="0$TTP"; fi
	/usr/bin/curl -k http://$HP/scripts/downloader/webscript.php?chatmediumid=$CHATMEDIUMTELEGRAMTYPE\&thread=$TTP\&threads=$NTP\&waitmsecs=$WT\&$HHM &
    TT=$(( TT+1 ))
    if [ $TT -eq $NT ]; then break; fi
done

exit 0
