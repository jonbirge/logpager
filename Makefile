# Define variables
IMAGE_NAME=logpager
VERSION=dev
DOCKER_HUB_USER=jonbirge


# Derived variables
FULL_IMAGE_NAME=$(DOCKER_HUB_USER)/$(IMAGE_NAME):$(VERSION)

# Build the Docker image
build:
	docker build -t $(FULL_IMAGE_NAME) .

# Push the Docker image to Docker Hub
push: build
	docker tag $(FULL_IMAGE_NAME) $(DOCKER_HUB_USER)/$(IMAGE_NAME):latest
	docker push $(DOCKER_HUB_USER)/$(IMAGE_NAME):latest

release: push
	docker push $(FULL_IMAGE_NAME)

# No cache build (a clear abuse of 'make clean')
no-cache:
	docker build -t $(FULL_IMAGE_NAME) --no-cache .

# Run locally for final testing
test: build
	docker run --name $(IMAGE_NAME)_test -d -p 8080:80 $(FULL_IMAGE_NAME)

# Iterate locally for development
dev: build
	docker run --name $(IMAGE_NAME)_test -d -p 8080:80 --volume=.:/var/www/:ro $(FULL_IMAGE_NAME)

# Stop the local test
stop:
	docker stop $(IMAGE_NAME)_test
	docker rm $(IMAGE_NAME)_test

# Convenience command to build and push
all: build

.PHONY: build all
