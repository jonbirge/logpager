#!/bin/bash

# Check if a command-line argument was provided
if [ $# -lt 1 ]; then
    echo "No arguments provided. Please provide the path to the IP file."
    exit 1
fi

# Path to the file containing the IP addresses and CIDRs
IP_FILE="$1"

# Set name
SET_NAME=auto_blocked

# Define a new ruleset
nft add table inet filter
nft add chain inet filter input { type filter hook input priority 0\; }

# Create a new set for the auto-blocked IPs/CIDRs
nft add set inet filter "$SET_NAME" { type ipv4_addr\; }

# Add new rules from file
while IFS= read -r ip
do
    if [[ "$ip" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+(/([0-9]|[1-2][0-9]|3[0-2]))?$ ]]; then
        nft add element inet filter "$SET_NAME" { "$ip" }
        echo "Blocking IP/CIDR: $ip"
    else
        echo "Skipping invalid IP/CIDR: $ip"
    fi
done < "$IP_FILE"

# Add a rule to drop packets from IPs in the set
nft add rule inet filter input ip saddr @"$SET_NAME" drop

echo "IP blocking complete."
