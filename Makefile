# Define variables
IMAGE_NAME=logpager
VERSION=1.8-dev
DOCKER_HUB_USER=jonbirge

# Derived variables
FULL_IMAGE_NAME=$(DOCKER_HUB_USER)/$(IMAGE_NAME):$(VERSION)

# Build the Docker image
build:
	docker build -t $(FULL_IMAGE_NAME) .
	docker tag $(FULL_IMAGE_NAME) $(DOCKER_HUB_USER)/$(IMAGE_NAME):latest

# No cache build (a clear abuse of 'make clean')
clean:
	docker build -t $(FULL_IMAGE_NAME) --no-cache .

# Push into the latest tag
push: build
	docker push $(DOCKER_HUB_USER)/$(IMAGE_NAME):latest

# Push into the latest tag and version tag
release: push
	docker push $(FULL_IMAGE_NAME)

# Test image for development
test:
	docker build -t $(IMAGE_NAME)_test .

# Bring up/down the test stack
up: test
	cd ./test/stack && ./up.sh

down:
	- cd ./test/stack && ./down.sh

# Convenience command to build
all: build

.PHONY: build clean test release stop run it all up down

