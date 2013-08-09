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

    sleep 5

    echo 'dummy-img' > $BUILD_INFO_FILE
    echo 'dummy-container' >> $BUILD_INFO_FILE
    echo '42' >> $BUILD_INFO_FILE

    exit 0    
}

# dummy

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# @todo implement a build:infos command that echoes these informations
#       instead of passing them as arguments, and just pass the build id
REF=$1
HASH=$2
CLONE_URL=$3
ACCESS_TOKEN=$4
COMMIT_NAME=$5
COMMIT_TAG=$6
BUILD_ID=$7

BUILD_JOB=$(docker run -d symfony2 buildapp $CLONE_URL $REF $HASH $ACCESS_TOKEN)
# BUILD_JOB=$(docker run -d symfony2 echo $CLONE_URL $REF $HASH $ACCESS_TOKEN)
RES=$?

if [ "$RES" != 0 ]; then
    exit $RES;
fi;

echo $BUILD_JOB > $BUILD_JOB_FILE

docker attach $BUILD_JOB

rm $BUILD_JOB_FILE

BUILD_IMG=$(docker commit $BUILD_JOB $COMMIT_NAME $COMMIT_TAG)

CONTEXT_DIR="/tmp/stage1/build-$COMMIT_NAME-$COMMIT_TAG/"
mkdir -p $CONTEXT_DIR

# insert ssh keys
SSH_KEY_PATH=$(basename $(/usr/bin/php $DIR/../app/console build:keys:dump $BUILD_ID -f $CONTEXT_DIR/id_rsa))
SSH_CONFIG=$(tempfile --directory=$CONTEXT_DIR)

cat > $SSH_CONFIG <<EOF
Host github.com
    Hostname github.com
    User git
    IdentityFile /root/id_rsa
    StrictHostChecking no
EOF

cat > $CONTEXT_DIR/Dockerfile <<EOF
FROM $COMMIT_NAME:$COMMIT_TAG
ADD ${SSH_KEY_PATH} /root/.ssh/id_rsa
ADD ${SSH_KEY_PATH}.pub /root/.ssh/id_rsa.pub
ADD $(basename $SSH_CONFIG) /root/.ssh/config
EOF

docker build -q -t $COMMIT_NAME:$COMMIT_TAG $CONTEXT_DIR > /dev/null 2> /dev/null

WEB_WORKER=$(docker run -d -p 80 $COMMIT_NAME:$COMMIT_TAG runapp)

PORT=$(docker port $WEB_WORKER 80)

echo $BUILD_IMG > $BUILD_INFO_FILE
echo $WEB_WORKER >> $BUILD_INFO_FILE
echo $PORT >> $BUILD_INFO_FILE