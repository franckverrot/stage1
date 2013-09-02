#!/bin/bash +x

set -e

trap 'error_handler $?' ERR
trap cleanup SIGTERM EXIT

# echo "------> starting build $1"

function cleanup {
    if [ -f $BUILD_JOB_FILE ]; then
        docker stop $(cat $BUILD_JOB_FILE) > /dev/null
        docker rm $(cat $BUILD_JOB_FILE) > /dev/null

        rm -f $BUILD_JOB_FILE
    fi
}

function error_handler {
    echo
    echo "---> Build failed ($(date))"
    cleanup
    exit $1    
}

# @todo move that to the CONTEXT_DIR
BUILD_INFO_FILE="/tmp/stage1-build-info"
BUILD_JOB_FILE="/tmp/stage1-build-job"

function dummy {
    echo 'This is some dummy output'
    echo 'This is some dummy error output'

    for n in $(seq 1 $1); do
        echo "line $n"
        sleep 1
    done

    echo 'dummy-img' > $BUILD_INFO_FILE
    echo 'dummy-container' >> $BUILD_INFO_FILE
    echo '42' >> $BUILD_INFO_FILE
    echo 'http://dummy.stage1.dev/' >> $BUILD_INFO_FILE

    exit 0
}

# dummy 15

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

CONSOLE=$(realpath $DIR/../../app/console) || false

# php $CONSOLE build:infos $1

$(php $CONSOLE build:infos $1) || false

BUILD_URL="http://$BUILD_DOMAIN/"
BUILD_REDIS_LIST="frontend:$BUILD_DOMAIN"

# insert ssh keys
CONTEXT_DIR="/tmp/stage1/build-${COMMIT_NAME}-${COMMIT_TAG}/"
mkdir -p $CONTEXT_DIR

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

docker build -q -t ${COMMIT_NAME}:${COMMIT_TAG} $CONTEXT_DIR > /dev/null 2> /dev/null

rm -rf $CONTEXT_DIR

# @todo use the new -cidfile option
# echo docker run -d ${COMMIT_NAME}:${COMMIT_TAG} buildapp "$SSH_URL" "$REF" "$HASH" "$ACCESS_TOKEN"
BUILD_JOB=$(docker run -d ${COMMIT_NAME}:${COMMIT_TAG} buildapp "$SSH_URL" "$REF" "$HASH" "$ACCESS_TOKEN") || false

# BUILD_JOB_FILE is used in case we trap a SIGTERM
echo $BUILD_JOB > $BUILD_JOB_FILE

docker attach $BUILD_JOB

EXIT_CODE=$(docker inspect $BUILD_JOB | grep ExitCode | grep -Eo '[0-9]+') || false

if [ "$EXIT_CODE" != 0 ]; then
    error_handler $EXIT_CODE
fi

rm $BUILD_JOB_FILE

BUILD_IMG=$(docker commit $BUILD_JOB $COMMIT_NAME $COMMIT_TAG) || false

WEB_WORKER=$(docker run -d -p 80 -p 22 ${COMMIT_NAME}:${COMMIT_TAG} runapp) || false

PORT=$(docker port $WEB_WORKER 80) || false

redis-cli DEL $BUILD_REDIS_LIST > /dev/null
redis-cli RPUSH $BUILD_REDIS_LIST ${COMMIT_NAME}:${COMMIT_TAG} "http://127.0.0.1:$PORT/" > /dev/null

echo
echo "---> Build finished ($(date))"

echo $BUILD_IMG > $BUILD_INFO_FILE
echo $WEB_WORKER >> $BUILD_INFO_FILE
echo $PORT >> $BUILD_INFO_FILE
echo $BUILD_URL >> $BUILD_INFO_FILE