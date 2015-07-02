#!/bin/bash

# @author Clemens Westrup
# @date 07.07.2014

# This is a script to automatically synchronize a local project folder to a
# folder on a cluster server via a middle server.
# It watches the local folder for changes and recreates the local state on the
# target machine as soon as a change is detected.

# https://github.com/aalto-ics-kepaco/fswatch-rsync

################################################################################

# Set up your path to fswatch here if you don't want to / can't add it
# globally to your PATH variable (default is "fswatch" when specified in PATH).
# e.g. FSWATCH_PATH="/Users/you/builds/fswatch/fswatch"
FSWATCH_PATH="fswatch"

# Sync latency / speed in seconds
LATENCY="2"

# check color support
colors=$(tput colors)
if (($colors >= 8)); then
    red='\033[0;31m'
    green='\033[0;32m'
    nocolor='\033[00m'
else
  red=
  green=
  nocolor=
fi

# Check compulsory arguments
if [[ "$1" = "" || "$2" = "" ]]; then
  echo -e "${red}Error: Takes 2 compulsory arguments.${nocolor}"
  echo -n "Usage: fswatch-rsync.sh /source/path /target/path"
  exit
else
  SOURCE_PATH="$1"
  TARGET_PATH="$2"
fi

# Welcome
echo      ""
echo      "Source path:  \"$SOURCE_PATH\""
echo      "Target path: \"$TARGET_PATH\""
echo      ""
echo -n   "Performing initial complete synchronization "
echo -n   "(Warning: Target directory will be overwritten "
echo      "with local version if differences occur)."

# Perform initial complete sync
read -n1 -r -p "Press any key to continue (or abort with Ctrl-C)... " key
echo      ""
echo -n   "Synchronizing... "
rsync -avzr -q --delete --force \
--exclude=".*/" --exclude=".*" \
--exclude="*___jb_bak___" --exclude="*___jb_old___" \
--exclude="*.lock" \
$SOURCE_PATH $TARGET_PATH
echo      "done."
echo      ""

# Watch for changes and sync (exclude hidden files)
echo    "Watching for changes. Quit anytime with Ctrl-C."
${FSWATCH_PATH} -0 -r -l $LATENCY $SOURCE_PATH \
--exclude="/\.*/$" --exclude="/\.[^/]*$" \
--exclude=".*___jb_bak___$" --exclude=".*___jb_old___$" \
--exclude=".*\.lock$" \
| while read -d "" event
  do
    echo $event > .tmp_files
    echo -en "${green}" `date` "${nocolor}\"$event\" changed. Synchronizing... "
    rsync -avzr -q --delete --force \
    --include-from=.tmp_files \
    $SOURCE_PATH $TARGET_PATH
    echo "done."
    rm -rf .tmp_files
  done
