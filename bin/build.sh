#!/bin/bash

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

BUILD_ID=$1
CLONE_URL=$2
ACCESS_TOKEN=$3
COMMIT_NAME=$4

BUILD_JOB=$(docker run -d symfony2 buildapp $CLONE_URL $ACCESS_TOKEN)

docker attach $BUILD_JOB > /tmp/stage1-build-output
docker wait $BUILD_JOB > /dev/null

php $DIR/../app/console build:record $BUILD_ID < /tmp/stage1-build-output

echo > /tmp/stage1-build-output

BUILD_IMG=$(docker commit $BUILD_JOB $COMMIT_NAME)

WEB_WORKER=$(docker run -d -p 80 $BUILD_IMG runapp)

PORT=$(docker port $WEB_WORKER 80)

echo $BUILD_IMG
echo $WEB_WORKER
echo $PORT