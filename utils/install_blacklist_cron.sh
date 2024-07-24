#!/bin/bash

# Check if two command-line arguments were provided
if [ $# -ne 1 ]; then
    echo "Incorrect number of arguments provided. Please provide the path to the blacklist file."
    exit 1
fi

# Path to the UFW update script and the blacklist file
BLACKLIST_FILE="$1"
UFW_SCRIPT="$(pwd)/nft_update.sh"

# Ensure the script is executable
chmod +x "$UFW_SCRIPT"

# Write out current root crontab and add new cron job
(crontab -l 2>/dev/null; echo "*/10 * * * * $UFW_SCRIPT $BLACKLIST_FILE") | crontab -

echo "Cron job set up to run $UFW_SCRIPT with $BLACKLIST_FILE every 10 minutes."

