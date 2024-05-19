#!/bin/bash

# Check if a command-line argument was provided
if [ $# -lt 1 ]; then
    echo "No arguments provided. Please provide the path to the IP blacklist file."
    exit 1
fi

# Path to the file containing the IP addresses and CIDRs
IP_FILE="$1"

# Set name
SET_NAME=auto_blocked

# Define a new ruleset
nft add table inet filter

# Delete the chains that contain the rule referencing the auto_blocked set, if they exist
echo "Deleting old chains..."
nft delete chain inet filter "${SET_NAME}_input" || true
nft delete chain inet filter "${SET_NAME}_forward" || true

# Create new chains for the auto-blocked IPs/CIDRs
echo "Creating new chains..."
nft add chain inet filter "${SET_NAME}_input" { type filter hook input priority -1000\; }
nft add chain inet filter "${SET_NAME}_forward" { type filter hook forward priority -1000\; }

# Delete the auto_blocked set if it exists
nft delete set inet filter "$SET_NAME" || true

# Create a new set for the auto-blocked IPs/CIDRs, with the 'flags interval' option
echo "Creating new set..."
nft add set inet filter "$SET_NAME" { type ipv4_addr\; flags interval\; }

# Add new rules from file
echo "Reading IP addresses and CIDRs from file..."
while IFS= read -r ip
do
    if [[ "$ip" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+(/([0-9]|[1-2][0-9]|3[0-2]))?$ ]]; then
        nft add element inet filter "$SET_NAME" { "$ip" }
        # echo "Blocking IP/CIDR: $ip"
    else
        echo "Skipping invalid IP/CIDR: $ip"
    fi
done < "$IP_FILE"

# Add rules to drop packets from IPs in the set
echo "Adding rules to drop packets..."
nft add rule inet filter "${SET_NAME}_input" ip saddr @"$SET_NAME" drop
nft add rule inet filter "${SET_NAME}_forward" ip saddr @"$SET_NAME" drop

echo "IP blocking complete!"
