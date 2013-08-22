#!/bin/bash

CONTAINER_ID=$1
IMAGE_ID=$2
IMAGE_TAG=$3

docker stop $CONTAINER_ID
docker rm $CONTAINER_ID
docker rmi $IMAGE_ID