#!/bin/bash
#
# based on:
# EDWARDS RESEARCH
# www.edwards-research.com
# This converts the audio from .mp4 files that include video (e.g. youtube.com streams) to
# .mp3 files.

if [ "$1" = "-silent" ] ; then
    SILENT=yes
    shift
fi
if [ "$1" = "-delete" ] ; then
    DELETE=yes
    shift
fi
if [ "$1" = "" -o "$2" = "" ] ; then
	echo "usage: $0 [-silent] [-delete] <mpeg-filename> <mp3-filename>"
    exit 1
fi
 
if [[ -z "$1" ]] ; then
	if [ "$SILENT" = "" ] ; then
    	echo "source file not found."
    fi
    exit 1
fi
 
# Dump audio from .mp4 to .wav with mplayer
#   So, it looks as if it doesn't make a difference in terms of the output (at least from
#   my small test group) whether you pick pcm:waveheader or pcm:fast. pcm:waveheader takes
#   more than twice as long to convert but pcm:fast complains.  I'm going to leave it at
#   waveheader because I'm not in a rush and I'd rather not have the warnings.  Feel free
#   to change this to pcm:fast and experiment.
#       -ao pcm:waveheader -> 59 seconds, 4625553 byte .mp3
#       -ao pcm:fast       -> 22 seconds, 4625553 byte .mp3
#   mplayer  -vc null -vo null -nocorrect-pts -ao pcm:fast "${FILE}"
TMPFILE=/tmp/$(basename "$1").tmp.wav
#mplayer -vc null -vo null -nocorrect-pts -ao pcm:waveheader:file="$TMPFILE" "$1"
if [ "$SILENT" = "" ] ; then
	mplayer -vc null -vo null -nocorrect-pts -ao pcm:waveheader:fast:file="$TMPFILE" "$1"
else
	mplayer -vc null -vo null -nocorrect-pts -ao pcm:waveheader:fast:file="$TMPFILE" "$1" >/dev/null 2>&1
fi
RV=$?
if [[ $RV != 0 ]] ; then
    if [ "$SILENT" = "" ] ; then
    	echo "mplayer completed unsuccessfully."
    fi
    exit 1
fi
 
# Convert .wav to .mp3
if [ "$SILENT" = "" ] ; then
	lame -h -b 192 "$TMPFILE" "$2"
else
	lame -h -b 192 "$TMPFILE" "$2" >/dev/null 2>&1
fi
RV=$?
if [[ $RV != 0 ]] ; then
    if [ "$SILENT" = "" ] ; then
    	echo "lame completed unsuccessfully."
    fi
    exit 1
fi
 
# Cleanup Temporary File
rm $TMPFILE

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
