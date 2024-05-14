#!/bin/bash

# Check if two command-line arguments were provided
if [ $# -ne 2 ]; then
    echo "Incorrect number of arguments provided. Please provide the paths to the UFW script and the blacklist file."
    exit 1
fi

# Path to the UFW update script and the blacklist file
UFW_SCRIPT="$1"
BLACKLIST_FILE="$2"

# Ensure the script is executable
chmod +x "$UFW_SCRIPT"

# Write out current root crontab and add new cron job
(sudo crontab -l 2>/dev/null; echo "*/5 * * * * $UFW_SCRIPT $BLACKLIST_FILE") | sudo crontab -

echo "Cron job set up to run $UFW_SCRIPT with $BLACKLIST_FILE every 5 minutes as root."
