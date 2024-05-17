#!/bin/bash

# Set name
SET_NAME=auto_blocked

# Delete the rule that drops packets from IPs in the set
echo "Deleting rule to drop packets..."
nft delete rule inet filter "$SET_NAME" ip saddr @"$SET_NAME" drop || true

# Delete the chain for the auto-blocked IPs/CIDRs
echo "Deleting chain..."
nft delete chain inet filter "$SET_NAME" || true

# Delete the table
echo "Deleting table..."
nft delete table inet filter || true

# Delete the auto_blocked set
echo "Deleting set..."
nft delete set inet filter "$SET_NAME" || true

echo "IP clean-up complete!"
