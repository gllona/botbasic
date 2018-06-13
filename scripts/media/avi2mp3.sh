#!/bin/bash
#
# avi2mp3
# Extracts audio (as MP3) from an AVI video file

if [ "$1" = "-silent" ] ; then
    SILENT=yes
    shift
fi
if [ "$1" = "-delete" ] ; then
    DELETE=yes
    shift
fi
if [ "$1" = "" -o "$2" = "" ]
then
	echo "usage: $0 [-silent] [-delete] <avi-filename> <mp3-filename>"
    exit 1
fi

if [[ -z "$1" ]] ; then
    if [ "$SILENT" = "" ] ; then
    	echo "source file not found."
    fi
    exit 1
fi

# ffmpeg -i $1 -vn -ar 44100 -ac 2 -ab 192 -f mp3 $2
# previous one doesn't work in my linux debian (catsuki3)
if [ "$SILENT" = "" ] ; then
	mplayer -dumpaudio -dumpfile $2 $1
else
	mplayer -dumpaudio -dumpfile $2 $1 >/dev/null 2>&1
fi
RV=$?
if [[ $RV != 0 ]] ; then
    if [ "$SILENT" = "" ] ; then
	    echo "mplayer completed unsuccessfully."
	fi
    exit 1
fi

# Optionally delete original file 
if [ "$DELETE" = "yes" ] ; then
    rm "$1" 2>/dev/null
    RV=$?
    if [[ $RV != 0 ]] ; then
        if [ "$SILENT" = "" ] ; then
            echo "couldn't delete original file."
        fi
    fi
fi

if [ "$SILENT" = "" ] ; then
	echo "conversion complete."
fi
exit 0
