# Define variables
IMAGE_NAME=logpager
SMALL_SUFFIX=-small
VERSION=1.8-dev
DOCKER_HUB_USER=jonbirge
DOCKERFILE_SMALL=Dockerfile-small

# Derived variables
SRC_FILES=$(shell find ./src -type f)
BASE_NAME=$(DOCKER_HUB_USER)/$(IMAGE_NAME)
RELEASE_IMAGE_NAME=$(BASE_NAME):$(VERSION)
RELEASE_IMAGE_NAME_SMALL=$(BASE_NAME):$(VERSION)$(SMALL_SUFFIX)
LATEST_IMAGE_NAME=$(BASE_NAME):latest
LATEST_IMAGE_NAME_SMALL=$(BASE_NAME):latest$(SMALL_SUFFIX)

# Build the standard Docker image
build: $(SRC_FILES) Dockerfile
	docker build -t $(LATEST_IMAGE_NAME) .

# Build the small Docker image
build-small: $(SRC_FILES) $(DOCKERFILE_SMALL)
	docker build -t $(LATEST_IMAGE_NAME_SMALL) -f $(DOCKERFILE_SMALL) .

# No cache build (a clear abuse of 'make clean')
clean:
	docker build -t $(LATEST_IMAGE_NAME_SMALL) -f $(DOCKERFILE_SMALL) --no-cache .
	docker build -t $(LATEST_IMAGE_NAME) --no-cache .

# Push into the latest tag
push: build
	docker push $(LATEST_IMAGE_NAME)

# Push into the latest tag and version tag
release: push build-small
	docker tag $(LATEST_IMAGE_NAME_SMALL) $(RELEASE_IMAGE_NAME_SMALL)
	docker tag $(LATEST_IMAGE_NAME) $(RELEASE_IMAGE_NAME)
	docker push $(RELEASE_IMAGE_NAME_SMALL)
	docker push $(RELEASE_IMAGE_NAME)

# Test image for development
test:
	docker build -t $(IMAGE_NAME)_test .

# Bring up/down the test stack
up: test
	cd ./test/stack && ./up.sh

down:
	- cd ./test/stack && ./down.sh

# Convenience command to build
all: build build-small

.PHONY: build build-small clean test release stop run it all up down
