#!/bin/bash

source /usr/local/lib/stage1.sh

# @todo move that to container ENV (maybe from Project's config)
export APP_ROOT=/app

if [ -d $APP_ROOT ]; then
    rm -rf $APP_ROOT
fi

if [ "$IS_PULL_REQUEST" = "1" ]; then
    stage1_exec "git clone --quiet --depth 1 $SSH_URL $APP_ROOT"
    cd $APP_ROOT
    stage1_exec "git fetch --quiet origin refs/$REF"
    stage1_exec "git checkout --quiet -b pull_request FETCH_HEAD"
else
    stage1_exec "git clone --quiet --depth 1 --branch $REF $SSH_URL $APP_ROOT"
fi

cd $APP_ROOT

# composer configuration to avoid hitting github's api rate limit
if [ -f composer.json ]; then
    # stage1_announce "configuring composer with token $ACCESS_TOKEN"
    stage1_composer_configure $ACCESS_TOKEN
fi

# check if we must (or want to) use the local build.yml configuration
if [ -n "$FORCE_LOCAL_BUILD_YML" ]; then
    stage1_announce "forcing local .build.yml"
    cp /root/build_local.yml ./.build.yml
elif [[ ( ! -f ./.build.yml && -f /root/build_local.yml ) ]]; then
    stage1_announce "no .build.yml found, using local .build.yml"
    cp /root/build_local.yml ./.build.yml
fi

STAGE1_STAGE='build'

if [ -x /usr/local/bin/stage1_image_init_build ]; then
    stage1_image_init_build
fi

/usr/local/bin/yuhao_build