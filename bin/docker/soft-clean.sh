#!/bin/bash -x

XARGS="xargs --no-run-if-empty"

docker ps -aq | $XARGS -n 1 docker rm
docker images | grep -E 'b/|none' | awk '{print $3}' | $XARGS docker rmi
