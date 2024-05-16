#!/bin/bash

# Flush the auto_blocked set
nft flush set inet filter auto_blocked

echo "All auto-blocked rules have been removed."
