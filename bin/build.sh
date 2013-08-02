#!/bin/bash

BUILD_INFO_FILE="/tmp/stage1-build-info"

# echo 'This is some dummy output'
# echo 'This is some dummy error output'

# sleep 10

# echo 'dummy-img' > $BUILD_INFO_FILE
# echo 'dummy-container' >> $BUILD_INFO_FILE
# echo '42' >> $BUILD_INFO_FILE

# exit 0

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

BUILD_ID=$1
CLONE_URL=$2
ACCESS_TOKEN=$3
COMMIT_NAME=$4

BUILD_JOB=$(docker run -d symfony2 buildapp $CLONE_URL $ACCESS_TOKEN)

docker attach $BUILD_JOB

BUILD_IMG=$(docker commit $BUILD_JOB $COMMIT_NAME)

WEB_WORKER=$(docker run -d -p 80 $BUILD_IMG runapp)

PORT=$(docker port $WEB_WORKER 80)

echo $BUILD_IMG > $BUILD_INFO_FILE
echo $WEB_WORKER >> $BUILD_INFO_FILE
echo $PORT >> $BUILD_INFO_FILE