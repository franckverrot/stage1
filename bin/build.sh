#!/bin/bash

BUILD_INFO_FILE="/tmp/stage1-build-info"
BUILD_JOB_FILE="/tmp/stage1-build-job"

function cleanup {
    if [ -f $BUILD_JOB_FILE ]; then
        docker stop $BUILD_JOB_FILE
        docker rm $BUILD_JOB_FILE
    fi
    
    rm -f $BUILD_INFO_FILE $BUILD_JOB_FILE
}

trap cleanup SIGTERM

function dummy {
    echo 'This is some dummy output'
    echo 'This is some dummy error output'

    sleep 1d

    echo 'dummy-img' > $BUILD_INFO_FILE
    echo 'dummy-container' >> $BUILD_INFO_FILE
    echo '42' >> $BUILD_INFO_FILE

    exit 0    
}

# dummy

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

REF=$1
HASH=$2
CLONE_URL=$3
ACCESS_TOKEN=$4
COMMIT_NAME=$5
COMMIT_TAG=$6

BUILD_JOB=$(docker run -d symfony2 buildapp $CLONE_URL $REF $HASH $ACCESS_TOKEN)
RES=$?

if [ "$RES" != 0 ]; then
    exit $RES;
fi;

echo $BUILD_JOB > $BUILD_JOB_FILE

docker attach $BUILD_JOB

rm $BUILD_JOB_FILE

BUILD_IMG=$(docker commit $BUILD_JOB $COMMIT_NAME $COMMIT_TAG)

WEB_WORKER=$(docker run -d -p 80 $BUILD_IMG runapp)

PORT=$(docker port $WEB_WORKER 80)

echo $BUILD_IMG > $BUILD_INFO_FILE
echo $WEB_WORKER >> $BUILD_INFO_FILE
echo $PORT >> $BUILD_INFO_FILE