#!/usr/bin/env bash

function diff_units {
	CURRENT_VALUE="$1"
	CURRENT_TIME=$(date +%s.%N)
	TMP_FILE="/tmp/diff_units.$2.tmp"
	OLD_TIME=$([[ -f "$TMP_FILE" ]] && cat "$TMP_FILE" | head -n1 || echo "0")
	OLD_VALUE=$([[ -f "$TMP_FILE" ]] && cat "$TMP_FILE" | tail -n1 || echo "0")
	PER_SEC=$(perl -e "printf('%.2f', ($CURRENT_VALUE - $OLD_VALUE) / ($CURRENT_TIME - $OLD_TIME))")
	echo $CURRENT_TIME > $TMP_FILE && echo $CURRENT_VALUE >> $TMP_FILE
	echo $PER_SEC;
}

function get_uptime_int {
	echo `cat /proc/uptime | awk '{printf "%0.f", $1}'`
}

function get_bandwidth {
	RX_FILE=$(mktemp)
	TX_FILE=$(mktemp)
	# Received: field 3, Transmitted: field 7
	# We have to use temporary files because while loop runs in subshell.
	# That means the variables defined inside won't be preserved.
	netstat -i | tail -n+3 | while read -r LINE; do
		echo $LINE | tr -s ' ' | cut -d' ' -f3 >> $RX_FILE
		echo $LINE | tr -s ' ' | cut -d' ' -f7 >> $TX_FILE
	done
	RX_TOTAL=$(awk '{s+=$1} END {print s}' $RX_FILE)
	TX_TOTAL=$(awk '{s+=$1} END {print s}' $TX_FILE)
	printf "$RX_TOTAL\n$TX_TOTAL"
}

# Load score based on load average divided by number of CPUs
# Less than 1: CPUs are under-utilized.
# Exactly 1: CPUs are ideally utilized.
# More than 1: Need more CPUs to do the work they need to do.
function get_load_score {
	LOAD=$(cat /proc/loadavg | cut -d" " -f1)
	CPU_COUNT=$(grep -c ^processor /proc/cpuinfo)
	perl -e "printf('%.2f', $LOAD / $CPU_COUNT)"
}

# Hostname
echo "machine.hostname="$(hostname)

# CPU information.
TMP=$(cat /proc/loadavg)
AVG_1=$(echo $TMP | cut -d" " -f1)
AVG_15=$(echo $TMP | cut -d" " -f3)
echo "machine.cpu.load_score="$(get_load_score)
echo "machine.cpu.load_avg_max="$(grep -c ^processor /proc/cpuinfo)
echo "machine.cpu.load_avg.1=$AVG_1"
echo "machine.cpu.load_avg.15=$AVG_15"
echo "machine.cpu.iowait="$(top -ibn1 | egrep -o "[0-9.]+ wa" | cut -d" " -f1)

# Gather memory information.
TMP=$(free -m)
echo "machine.memory.total="$(echo $TMP | grep -oP '\d+' | head -n1)
echo "machine.memory.used="$(echo $TMP | grep -oP '\d+' | head -n2 | tail -n1)
echo "machine.swap.total="$(printf "$TMP" | tail -n1 | grep -oP '\d+' | head -n1)
echo "machine.swap.used="$(printf "$TMP" | tail -n1 | grep -oP '\d+' | head -n2 | tail -n1)

# Gather other system information.
echo "machine.process_count="$(echo /proc/[0-9]* | wc -w)
echo "machine.uptime="$(get_uptime_int)

# Gather bandwidth stats.
TMP=$(get_bandwidth)
UPLOAD=$(echo "$TMP" | tail -n1)
DOWNLOAD=$(echo "$TMP" | head -n1)
echo "machine.network.upload_rate="$(diff_units $UPLOAD "upload")
echo "machine.network.download_rate="$(diff_units $DOWNLOAD "download")
