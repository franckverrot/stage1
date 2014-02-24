#!/bin/bash -ex

if [ -z "$1" -o ! -d "$1" ]; then
    docker build "$*" -t stage1 docker/stage1
    docker build "$*" -t php docker/php
    docker build "$*" -t symfony2 docker/symfony2
    docker build "$*" -t yuhao docker/yuhao
else
    docker build "$*" -t $1 docker/$1
fi
