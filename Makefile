# Define variables
IMAGE_NAME=logpager
VERSION=1.7
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

latest: build
	docker push $(DOCKER_HUB_USER)/$(IMAGE_NAME):latest

# Push the Docker image to Docker Hub
release: latest
	docker push $(FULL_IMAGE_NAME)

# Image with test files for development
dev:
	docker build -t $(IMAGE_NAME)_dev --build-arg TESTLOGS=true .

# Run test image
local: dev stop
	docker run --name $(IMAGE_NAME)_test -d -p 8080:80 --volume=./src:/var/www/:ro $(IMAGE_NAME)_dev

# Stop test image
stop:
	-docker stop $(IMAGE_NAME)_test
	-docker rm $(IMAGE_NAME)_test

# Convenience command to build
all: build

.PHONY: build clean dev release stop local all
