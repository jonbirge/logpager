# Define variables
IMAGE_NAME=logpager
VERSION=1.7
DOCKER_HUB_USER=jonbirge

# Derived variables
FULL_IMAGE_NAME=$(DOCKER_HUB_USER)/$(IMAGE_NAME):$(VERSION)

# Build the Docker image
build:
	docker build -t $(FULL_IMAGE_NAME) .

# No cache build (a clear abuse of 'make clean')
clean:
	docker build -t $(FULL_IMAGE_NAME) --no-cache .

# Push the Docker image to Docker Hub
push: build
	docker tag $(FULL_IMAGE_NAME) $(DOCKER_HUB_USER)/$(IMAGE_NAME):latest
	docker push $(DOCKER_HUB_USER)/$(IMAGE_NAME):latest
	docker push $(FULL_IMAGE_NAME)

release: push
	docker push $(FULL_IMAGE_NAME)

# Iterate locally for development
dev: stop
	docker build -t $(IMAGE_NAME)_dev --build-arg TESTLOGS=true .

test: dev
	docker run --name $(IMAGE_NAME)_test -d -p 8080:80 --volume=./src:/var/www/:ro $(IMAGE_NAME)_dev

stop:
	-docker stop $(IMAGE_NAME)_test
	-docker rm $(IMAGE_NAME)_test

# Convenience command to build
all: build

.PHONY: build no-cache push dev stop all
