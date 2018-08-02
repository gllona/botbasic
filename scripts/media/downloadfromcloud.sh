#!/usr/bin/env bash
#
# this if for gcloud

BUCKET=org-logicos-bots-dev/media

PATH=/snap/bin:$PATH
which gsutil
if [ $? != 0 ]; then
    echo "no gsutil command found"
    exit 1
fi

if [ $# -ne 2 ]; then
    echo "pass remotefile and localfile as args"
    exit 1
fi

RF=$1
LF=$2

mkdir -p $(dirname $LF)
gsutil cp gs://$BUCKET/$RF $LF

exit $?
