#!/bin/bash

# Check if a command-line argument was provided
if [ $# -eq 0 ]; then
    echo "No arguments provided. Please provide the path to the IP file."
    exit 1
fi

# Path to the file containing the IP addresses and CIDRs
IP_FILE="$1"
TAG="auto-blocked"

# Check if UFW is installed
if ! command -v ufw &> /dev/null
then
    echo "UFW is not installed. Please install UFW and run this script again."
    exit 1
fi

# Enable UFW if it's not already enabled
sudo ufw status | grep -q inactive
if [ $? -eq 0 ]; then
    echo "UFW is inactive. Enabling..."
    sudo ufw enable
fi

# Remove old rules tagged with $TAG
echo "Removing old firewall rules..."
sudo ufw status numbered | grep "($TAG)" | awk '{ print $1 }' | cut -d ']' -f1 | tr -d '[' | tac | while read num; do
    echo "Removing rule #$num"
    echo "y" | sudo ufw delete $num
done

# Add new rules from file
while IFS= read -r ip
do
    if [[ "$ip" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+(/([0-9]|[1-2][0-9]|3[0-2]))?$ ]]; then
        echo "Blocking IP/CIDR: $ip"
        sudo ufw insert 1 deny from "$ip" comment "$TAG"
    else
        echo "Skipping invalid IP/CIDR: $ip"
    fi
done < "$IP_FILE"

echo "IP blocking update complete."
