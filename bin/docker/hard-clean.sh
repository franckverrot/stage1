#!/bin/bash -x

XARGS="xargs --no-run-if-empty"

docker ps -q | $XARGS docker stop
docker ps -q -a | $XARGS docker rm
docker images | grep -E 'none|b/' | awk '{print $3}' | $XARGS docker rmi

if [ "$1" == "-f" ]; then
    echo "seriously cleaning..."

    sudo rm -rf /var/lib/docker/containers/*
    sudo rm -rf /var/lib/docker/graph/*
    sudo rm -rf /var/lib/docker/aufs/diff/*
    sudo rm -rf /var/lib/docker/aufs/mnt/*

    sudo restart docker
fi
