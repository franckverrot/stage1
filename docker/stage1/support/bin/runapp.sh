#!/bin/bash

# @todo move to symfony2 specific stuff

if [ ! -z "$DEBUG" ]; then
    set -x
fi

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

source $DIR/../lib/stage1.sh

/usr/sbin/sshd

stage1_announce 'generating run script'
php /root/yuhao/bin/yuhao -b /root/YuhaoDefaultBuilder.php run $APP_ROOT
cd $APP_ROOT && bash ./run.sh