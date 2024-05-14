#!/bin/bash

# Tag used for identifying rules created by ufw_update.sh
TAG="auto-blocked"

# Check if UFW is installed
if ! command -v ufw &> /dev/null
then
    echo "UFW is not installed. Please install UFW and run this script again."
    exit 1
fi

# Remove old rules tagged with $TAG
echo "Removing old firewall rules tagged with $TAG..."
sudo ufw status numbered | grep "($TAG)" | awk '{ print $1 }' | cut -d ']' -f1 | tr -d '[' | tac | while read -r num; do
    echo "Removing rule #$num"
    echo "y" | sudo ufw delete "$num"
done

echo "All tagged rules have been removed."
