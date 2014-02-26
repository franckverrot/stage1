#!/bin/bash

source /usr/local/lib/stage1.sh

APP_ROOT=/app

if [ -d $APP_ROOT ]; then
    rm -rf $APP_ROOT
fi

# stage1_announce "cloning repository $ssh_url"
stage1_exec "git clone --quiet --depth 1 --branch $REF $SSH_URL $APP_ROOT"

cd $APP_ROOT

# composer configuration to avoid hitting github's api rate limit
# @todo this has to be moved to the symfony builder
# but it must be ran even when the project provides a custom builder
# so maybe a $builder/bin/before script could be useful
if [ -f composer.json ]; then
    # stage1_announce "configuring composer with token $ACCESS_TOKEN"
    stage1_composer_configure $ACCESS_TOKEN
fi

if [ ! -f ./.build.yml -o -n "$FORCE_LOCAL_BUILD_YML" ]; then
    stage1_announce "using local .build.yml"
    cp /root/build_local.yml ./.build.yml
fi

STAGE1_STAGE='build'

if [ -x /usr/local/bin/stage1_image_init_build ]; then
    stage1_image_init_build
fi

/usr/local/bin/yuhao_build