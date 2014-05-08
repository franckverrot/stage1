#!/bin/bash

if [ ! -d /tmp/stage1 ]; then
    echo "sources not found, can't warmup"
fi

cd /tmp/stage1

git clone https://github.com/stage1/yuhao.git /tmp/yuhao
bin/docker/update.sh
bin/yuhao/update.sh