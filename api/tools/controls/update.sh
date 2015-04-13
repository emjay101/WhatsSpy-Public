#!/bin/sh
LOCKFILE="tracker.lock"

if [ -e "$LOCKFILE" ]; then
  echo "Cannot update while the tracker is running."
  exit 1
else
  cd ../
  git fetch --all
  git reset --hard origin/master
  echo "Update finished."
fi