#!/bin/bash

# Check if a command-line argument was provided
if [ $# -eq 0 ]; then
    echo "No arguments provided. Please provide the path to the IP file."
    exit 1
fi

# Path to the file containing the IP addresses and CIDRs
IP_FILE="$1"

# Flush existing rules (optional, be cautious with this)
nft flush ruleset

# Define a new ruleset
nft add table inet filter
nft add chain inet filter input { type filter hook input priority 0\; }

# Add new rules from file
while IFS= read -r ip
do
    if [[ "$ip" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+(/([0-9]|[1-2][0-9]|3[0-2]))?$ ]]; then
        nft add rule inet filter input ip saddr "$ip" drop
        echo "Blocking IP/CIDR: $ip"
    else
        echo "Skipping invalid IP/CIDR: $ip"
    fi
done < "$IP_FILE"

echo "IP blocking complete."
