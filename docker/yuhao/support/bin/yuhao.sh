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

# check if we must (or want to) use the local build.yml configuration
if [[ -n "$FORCE_LOCAL_BUILD_YML" || (! -f $APP_ROOT/.build.yml && -f /root/build_local.yml) ]]; then
    cp /root/build_local.yml $APP_ROOT/.build.yml
fi

yuhao.phar $APP_ROOT