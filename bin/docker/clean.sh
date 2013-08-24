#!/bin/bash

XARGS="xargs --no-run-if-empty"

docker ps -q | $XARGS docker stop
docker ps -q -a | $XARGS docker rm
docker images | grep -E 'none|b/' | awk '{print $3}' | $XARGS docker rmi

if [ "$1" == "-f" ]; then
    echo "seriously cleaning..."
    rm -rf /var/lib/docker/containers/*
    rm -rf /var/lib/docker/grap/*

    docker pull ubuntu:precise

    pkill docker
fi
