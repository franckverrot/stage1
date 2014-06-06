#!/bin/bash

set -e

APP_ROOT=/app

if [ "$IS_PULL_REQUEST" = "1" ]; then
    git clone --quiet --depth 1 $SSH_URL $APP_ROOT > /dev/null 2>&1
    cd $APP_ROOT
    git fetch --quiet origin refs/$REF > /dev/null  2>&1
    git checkout --quiet -b pull_request FETCH_HEAD > /dev/null
    cd - > /dev/null
else
    git clone --quiet --depth 1 --branch $REF $SSH_URL $APP_ROOT > /dev/null
fi

# check if we must (or want to) use the local .stage1.yml configuration
if [[ -n "$FORCE_LOCAL_STAGE1_YML" || (! -f $APP_ROOT/.stage1.yml && -f /root/stage1_local.yml) ]]; then
    cp /root/stage1_local.yml $APP_ROOT/.stage1.yml
fi

yuhao.phar $APP_ROOT
