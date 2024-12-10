# Define variables
IMAGE_NAME=logpager
SMALL_SUFFIX=-small
VERSION=1.9-dev
DOCKER_HUB_USER=jonbirge
BUILD_FILE=build/build.timestamp
BUILD_SMALL_FILE=build/build-small.timestamp
DOCKERFILE_SMALL=docker/Dockerfile_small

# Derived variables
SRC_FILES=$(shell find ./src -type f)
BASE_NAME=$(DOCKER_HUB_USER)/$(IMAGE_NAME)
RELEASE_IMAGE_NAME=$(BASE_NAME):$(VERSION)
RELEASE_IMAGE_NAME_SMALL=$(BASE_NAME):$(VERSION)$(SMALL_SUFFIX)
LATEST_IMAGE_NAME=$(BASE_NAME):latest
LATEST_IMAGE_NAME_SMALL=$(BASE_NAME):latest$(SMALL_SUFFIX)

# Convenience targets
all: build build-small
build: $(BUILD_FILE)
build-small: $(BUILD_SMALL_FILE)

# Build the standard Docker image
$(BUILD_FILE): $(SRC_FILES) Dockerfile
	docker build -t $(LATEST_IMAGE_NAME) .
	mkdir -p build
	touch $(BUILD_FILE)

# Build the small Docker image
$(BUILD_SMALL_FILE): $(SRC_FILES) $(DOCKERFILE_SMALL)
	docker build -t $(LATEST_IMAGE_NAME_SMALL) -f $(DOCKERFILE_SMALL) .
	mkdir -p build
	touch $(BUILD_SMALL_FILE)

# Remove cached builds
clean:
	docker builder prune --all -f
	rm -rf build

# Push into the latest tag
latest: $(BUILD_FILE)
	docker push $(LATEST_IMAGE_NAME)

# Push into the latest tag and version tag
release: push $(BUILD_SMALL_FILE)
	docker tag $(LATEST_IMAGE_NAME_SMALL) $(RELEASE_IMAGE_NAME_SMALL)
	docker tag $(LATEST_IMAGE_NAME) $(RELEASE_IMAGE_NAME)
	docker push $(RELEASE_IMAGE_NAME_SMALL)
	docker push $(RELEASE_IMAGE_NAME)

# Local test image for live development
dev:
	docker build -t $(IMAGE_NAME)-dev .

# Bring up/down the local dev stack
up: dev
	cd ./test/stack && ./up.sh

down:
	- cd ./test/stack && ./down.sh

.PHONY: all build build-small clean push release dev up down

