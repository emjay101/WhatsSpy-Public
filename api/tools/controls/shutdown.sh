#!/bin/sh
LOCKFILE="/var/lock/tracker.lock"

if [ -e "$LOCKFILE" ]; then
  	# Kill tracker if running
	pkill -SIGTERM -f "php tracker.php"
	rm -f "$LOCKFILE"
	echo "Tracker is shutdown."
else
  echo "No lock found"
  exit 1
fi

