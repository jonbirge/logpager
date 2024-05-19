#!/bin/bash

# Set name
SET_NAME=auto_blocked

# Delete the rules that drop packets from IPs in the set
echo "Deleting rules to drop packets..."
nft delete rule inet filter "${SET_NAME}_input" ip saddr @"$SET_NAME" drop || true
nft delete rule inet filter "${SET_NAME}_forward" ip saddr @"$SET_NAME" drop || true

# Delete the auto_blocked set
echo "Deleting set..."
nft delete set inet filter "$SET_NAME" || true

# Delete the chains for the auto-blocked IPs/CIDRs
echo "Deleting chains..."
nft delete chain inet filter "${SET_NAME}_input" || true
nft delete chain inet filter "${SET_NAME}_forward" || true

# Delete the table
echo "Deleting table..."
nft delete table inet filter || true

echo "IP unblocking complete!"
