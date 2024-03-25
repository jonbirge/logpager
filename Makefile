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
latest: build
	docker push $(DOCKER_HUB_USER)/$(IMAGE_NAME):latest

# Push into the latest tag and version tag
release: latest
	docker push $(FULL_IMAGE_NAME)

# Image with test files for development
test:
	docker build -f Dockerfile_test -t $(IMAGE_NAME)_dev .

# Run test image
run: stop test
	docker run --name $(IMAGE_NAME)_test -d -p 8080:80 --volume=./src:/var/www/:ro $(IMAGE_NAME)_dev

# Stop test image
stop:
	-docker stop $(IMAGE_NAME)_test
	-docker rm $(IMAGE_NAME)_test

# Convenience command to build
all: build

.PHONY: build clean dev release stop local all
