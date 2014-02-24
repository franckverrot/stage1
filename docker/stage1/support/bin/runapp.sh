#!/bin/bash

# @todo move to symfony2 specific stuff

if [ ! -z "$DEBUG" ]; then
    set -x
fi

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

source $DIR/../lib/stage1.sh

/usr/sbin/sshd

APP_ROOT=/var/www

stage1_announce 'running run script'
cd $APP_ROOT
/usr/local/bin/yuhao_run