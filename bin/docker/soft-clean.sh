#!/bin/bash -x

XARGS="xargs --no-run-if-empty"

docker ps -a | grep 'Exit 0' | awk '{print $1}' | $XARGS -n 1 docker rm
docker images -q | $XARGS docker rmi