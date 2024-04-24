# Define variables
IMAGE_NAME=logpager
VERSION=1.8-dev
DOCKER_HUB_USER=jonbirge

# Derived variables
REL_IMAGE_NAME=$(DOCKER_HUB_USER)/$(IMAGE_NAME):$(VERSION)
LATEST_IMAGE_NAME=$(DOCKER_HUB_USER)/$(IMAGE_NAME):latest

# Build the Docker image
build:
	docker build -t $(LATEST_IMAGE_NAME) .

# No cache build (a clear abuse of 'make clean')
clean:
	docker build -t $(LATEST_IMAGE_NAME) --no-cache .

# Push into the latest tag
push: build
	docker push $(LATEST_IMAGE_NAME)

# Push into the latest tag and version tag
release: push
	docker tag $(LATEST_IMAGE_NAME) $(RELEASE_IMAGE_NAME)
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

