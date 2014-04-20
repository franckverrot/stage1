#!/bin/bash -ex

XARGS="xargs --no-run-if-empty"

docker ps -a | grep 'Exit' | awk '{print $1}' | $XARGS -n 1 docker rm
docker images | grep -E 'b/|none' | awk '{print $1}' | $XARGS docker rmi