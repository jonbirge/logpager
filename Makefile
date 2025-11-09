# Define variables
IMAGE_NAME=logpager
VERSION=2.0-dev
DOCKER_HUB_USER=jonbirge
BUILD_FILE=build/build.timestamp

# Derived variables
SRC_FILES=$(shell find ./src -type f)
BASE_NAME=$(DOCKER_HUB_USER)/$(IMAGE_NAME)
RELEASE_IMAGE_NAME=$(BASE_NAME):$(VERSION)
LATEST_IMAGE_NAME=$(BASE_NAME):latest

# Convenience targets
all: build 
build: $(BUILD_FILE)

# Build the standard Docker image
$(BUILD_FILE): $(SRC_FILES) Dockerfile
	docker build -t $(LATEST_IMAGE_NAME) .
	mkdir -p build
	touch $(BUILD_FILE)

# Remove cached builds
clean:
	docker builder prune --all -f
	rm -rf build

# Push into the latest tag
latest: $(BUILD_FILE)
	docker push $(LATEST_IMAGE_NAME)

# Push into the latest tag and version tag
release: push $(BUILD_SMALL_FILE)
	docker tag $(LATEST_IMAGE_NAME) $(RELEASE_IMAGE_NAME)
	docker push $(RELEASE_IMAGE_NAME)

# Local test image for live development
dev:
	docker build -t $(IMAGE_NAME)-dev .

# Bring up/down the local dev stack
up: dev
	cd ./test/test-stack && ./up.sh

down:
	- cd ./test/test-stack && ./down.sh

.PHONY: all build clean push release dev up down

