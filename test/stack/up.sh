#!/bin/bash

# Check if access.log exists, if not, create it
if [ ! -f ./logs/access.log ]; then
    mkdir -p ./logs && touch ./logs/access.log
fi

# Check if blacklist.txt exists, if not, create it
if [ ! -f ./logs/blacklist.txt ]; then
    mkdir -p ./logs && touch ./logs/blacklist.txt
fi

# Run docker-compose
docker compose up --remove-orphans -d
