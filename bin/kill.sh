#!/bin/bash

BUILD_ID=$1
BUILD_JOB_FILE=/tmp/stage1-build-job
BUILD_INFO_FILE=/tmp/stage1-build-info

# pgrep+pkill would be nice, but its regexp support is shitty

function get_pid {
    ps auxwww | grep -E "build\.sh.+ $1\$" | awk '{print $2}'
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

    if [ -f $JOB_FILE ]; then
        BUILD_JOB=$(cat $BUILD_JOB_FILE)
        docker stop $BUILD_JOB
        docker rm $BUILD_JOB

        rm -f $BUILD_JOB_FILE $BUILD_INFO_FILE
    fi
fi