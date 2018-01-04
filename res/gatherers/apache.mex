#!/usr/bin/env bash

# Environment varables:
# APACHE_ACCESS_LOG
# APACHE_ERROR_LOG

GATHER_INTERVAL=3
WAIT_INTERVAL=$(expr $GATHER_INTERVAL + 1)

function lines_per_sec {
	HASH=$(echo -n $1 | md5sum | awk '{print $1}')
	TMPFILE="/tmp/mex_apache_lps_$HASH.tmp"
	(tail -n0 -f $1 & P=$! ; sleep $GATHER_INTERVAL; kill -9 $P) | wc -l > $TMPFILE &
	echo $TMPFILE
}

function grep_lines_per_sec {
	HASH=$(echo "$1 $2" | md5sum | awk '{print $1}')
	TMPFILE="/tmp/mex_apache_glps_{$HASH}.tmp"
	(tail -n0 -f $1 & P=$! ; sleep $GATHER_INTERVAL; kill -9 $P) | grep $2 | wc -l > $TMPFILE &
	echo $TMPFILE
}

V1_FILE=$(lines_per_sec $APACHE_ACCESS_LOG)
V2_FILE=$(lines_per_sec $APACHE_ERROR_LOG)
# V3_FILE=$(grep_lines_per_sec $APACHE_ACCESS_LOG "/special_endpoint")

# Wait for parallel subprocesses to finish.
sleep $WAIT_INTERVAL

V1=$(perl -e "printf('%.1f', $(cat $V1_FILE) / $GATHER_INTERVAL)")
V2=$(perl -e "printf('%.1f', $(cat $V2_FILE) / $GATHER_INTERVAL)")
# V3=$(perl -e "printf('%.1f', $(cat $V3_FILE) / $GATHER_INTERVAL)")

echo "apache.access_per_sec=$V1"
echo "apache.error_per_sec=$V2"
# echo "apache.api_per_sec=$V2"
