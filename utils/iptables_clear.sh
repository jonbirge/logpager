#!/bin/bash

# Set name
SET_NAME=auto_blocked

# Flush the auto_blocked set
nft flush set inet filter "$SET_NAME"

echo "All auto-blocked rules have been removed."
