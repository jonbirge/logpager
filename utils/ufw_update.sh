#!/bin/bash

# Check if the script received the correct number of arguments
if [ "$#" -ne 1 ]; then
    echo "Usage: $0 /path/to/ip_file.txt"
    exit 1
fi

# Path to the file containing the IP addresses and CIDRs
IP_FILE="$1"

# Tag used for identifying rules created by this script
TAG="auto-blocked"

# Check if UFW is installed
if ! command -v ufw &> /dev/null; then
    echo "UFW is not installed. Please install UFW and run this script again."
    exit 1
fi

# Enable UFW if it's not already enabled
if ufw status | grep -q inactive; then
    echo "UFW is inactive. Enabling..."
    ufw enable
fi

# Add new rules from file if they don't already exist
while IFS= read -r ip; do
    if [[ "$ip" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+(/([0-9]|[1-2][0-9]|3[0-2]))?$ ]]; then
        if ! ufw status | grep -q "$ip"; then
            echo "Blocking IP/CIDR: $ip"
            ufw insert 1 deny from "$ip" comment "$TAG"
        else
            echo "Rule for $ip already exists. Skipping..."
        fi
    else
        echo "Skipping invalid IP/CIDR: $ip"
    fi
done < "$IP_FILE"

echo "IP blocking complete."

