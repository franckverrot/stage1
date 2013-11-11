#!/bin/bash

# set empty to disable debug
DEBUG=""

if [ ! -z "$DEBUG" ]; then
    set -x
fi

set -e

trap 'error_handler $?' ERR
trap cleanup SIGTERM EXIT

if [ ! -d /tmp/run/build ]; then
    mkdir -p /tmp/run/build
fi

PID=$$

echo $PID > /tmp/run/build/$1.pid

function stage1_websocket_step {
    stage1_websocket_message "build.step" "{ \"step\": \"$1\" }"
}

function stage1_websocket_message {
    echo "[websocket:$1:$2]"
}

function debug {
    if [ -n "$DEBUG" ]; then
        echo "$@"
    fi
}

function cleanup {
    if [ -f $BUILD_JOB_FILE ]; then
        docker stop $(cat $BUILD_JOB_FILE) > /dev/null
        docker rm $(cat $BUILD_JOB_FILE) > /dev/null

        rm -f $BUILD_JOB_FILE
    fi

    rm -f /tmp/run/build/$1.pid
}

function error_handler {
    echo
    echo "------> Build failed ($(date))"
    cleanup $PID
    exit $1    
}

debug "------> starting build $1 ($(date))"


function dummy {
    echo 'This is some dummy output'
    echo 'This is some dummy error output' >&2

    for n in $(seq 1 $1); do
        echo "line $n"
        sleep 1
    done

    echo 'dummy-img' > $BUILD_INFO_FILE
    echo 'dummy-container' >> $BUILD_INFO_FILE
    echo '42' >> $BUILD_INFO_FILE

    exit 0
}

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

CONSOLE=$(realpath $DIR/../../app/console) || false

debug php $CONSOLE build:infos $1

$(php $CONSOLE build:infos $1) || false

BUILD_INFO_FILE="/tmp/stage1/build/$BUILD_ID/info"
BUILD_JOB_FILE="/tmp/stage1/build/$BUILD_ID/job"

BUILD_REDIS_LIST="frontend:$BUILD_HOST"

# CONTEXT_DIR="/tmp/stage1/build-${COMMIT_NAME}-${COMMIT_TAG}/"
CONTEXT_DIR="/tmp/stage1/build/$BUILD_ID/context"
mkdir -p $CONTEXT_DIR

# resources limitations
MEMORY_LIMIT=$((64*1024*1024))
CPU_SHARES=1

# dummy 5

debug '------> preparing building container'
stage1_websocket_step "prepare_build_container"

# insert ssh keys

SSH_KEY_PATH=$(basename $(php $CONSOLE build:keys:dump $BUILD_ID -f $CONTEXT_DIR/id_rsa)) || false
SSH_CONFIG=$(tempfile --directory=$CONTEXT_DIR) || false

cat > $SSH_CONFIG <<EOF
Host github.com
    Hostname github.com
    User git
    IdentityFile /root/.ssh/id_rsa
    StrictHostKeyChecking no
EOF

cat > $CONTEXT_DIR/Dockerfile <<EOF
FROM symfony2:latest
ADD ${SSH_KEY_PATH} /root/.ssh/id_rsa
ADD ${SSH_KEY_PATH}.pub /root/.ssh/id_rsa.pub
ADD $(basename $SSH_CONFIG) /root/.ssh/config
RUN chmod -R 0600 /root/.ssh
RUN chown -R root:root /root/.ssh
EOF

debug '------> building build container'
debug "------> docker build -q -t ${COMMIT_NAME} $CONTEXT_DIR > /dev/null 2> /dev/null"


docker build -q -t ${COMMIT_NAME} $CONTEXT_DIR > /dev/null 2> /dev/null

rm -rf $CONTEXT_DIR

debug '------> starting actual build'

# @todo use the new -cidfile option
debug '------> ' docker run -d ${COMMIT_NAME} buildapp "$SSH_URL" "$REF" "$HASH" "$ACCESS_TOKEN"
BUILD_JOB=$(docker run -e DEBUG=${DEBUG} -d -c ${CPU_SHARES} -m ${MEMORY_LIMIT} ${COMMIT_NAME} buildapp "$SSH_URL" "$REF" "$HASH" "$ACCESS_TOKEN") || false

# BUILD_JOB_FILE is used in case we trap a SIGTERM
echo $BUILD_JOB > $BUILD_JOB_FILE

docker attach $BUILD_JOB

EXIT_CODE=$(docker inspect $BUILD_JOB | grep ExitCode | grep -Eo '[0-9]+') || false

if [ "$EXIT_CODE" != 0 ]; then
    error_handler $EXIT_CODE
fi

rm $BUILD_JOB_FILE

# sometimes docker needs a short delay before being able to commit
# see https://github.com/progrium/buildstep/pull/23
sleep 5

BUILD_IMG=$(docker commit $BUILD_JOB $COMMIT_NAME) || false

WEB_WORKER=$(docker run -e DEBUG=${DEBUG} -d -p 80 -p 22 -c ${CPU_SHARES} -m ${MEMORY_LIMIT} ${COMMIT_NAME} runapp) || false

PORT=$(docker port $WEB_WORKER 80) || false

# @todo this should be done by the BuildConsumer
redis-cli DEL $BUILD_REDIS_LIST > /dev/null
redis-cli RPUSH $BUILD_REDIS_LIST ${COMMIT_NAME} "http://127.0.0.1:$PORT/" > /dev/null

echo
echo "------> Build finished ($(date))"

echo $BUILD_IMG > $BUILD_INFO_FILE
echo $WEB_WORKER >> $BUILD_INFO_FILE
echo $PORT >> $BUILD_INFO_FILE