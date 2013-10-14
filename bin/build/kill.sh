#!/bin/bash

BUILD_ID=$1
BUILD_JOB_FILE=/tmp/stage1-build-job
BUILD_INFO_FILE=/tmp/stage1-build-info

function get_pid {
    cat /tmp/run/build/$1.pid 2> /dev/null
}

PID=$(get_pid $BUILD_ID)

echo "Looking for $PID"

if [ -z "$PID" ]; then
    echo 'Nothing to kill.' >&2
    exit 1
fi

echo 'Sending SIGTERM.'
kill $PID

# stopping a docker container can take a bit of time
sleep 10

PID=$(get_pid $BUILD_ID)

if [ ! -z "$PID" ]; then
    echo 'Tired of your shit, sending SIGKILL.'
    kill -9 $PID

    rm -f /tmp/run/build/$1.pid

    if [ -f $BUILD_JOB_FILE ]; then
        BUILD_JOB=$(cat $BUILD_JOB_FILE)
        docker stop $BUILD_JOB
        docker rm $BUILD_JOB

        rm -f $BUILD_JOB_FILE $BUILD_INFO_FILE
    fi
fi