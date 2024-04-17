#!/bin/bash

# Check if access.log exists, if not, create it
if [ ! -f ./logs/access.log ]; then
    mkdir -p ./logs && touch ./logs/access.log
fi

# Run docker-compose
docker compose up --remove-orphans -d

