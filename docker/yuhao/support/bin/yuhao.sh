#!/bin/bash

set -e

if [ -z "$1" -o -z "$2" ]; then
    echo "Usage: $(basename $0) <repo> <ref>"
    exit 1
fi

APP_ROOT=/app

git clone --depth 1 --branch $2 $1 $APP_ROOT > /dev/null

if [ ! -f $APP_ROOT/.build.yml -o -n "$FORCE_LOCAL_BUILD_YML" ]; then
    cp /root/build_local.yml $APP_ROOT/.build.yml
fi

yuhao.phar $APP_ROOT