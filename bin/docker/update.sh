#!/bin/bash -ex

if [ -z "$1" -o ! -d "$1" ]; then
    docker build --rm --tag stage1/base $* docker/base
    docker build --rm --tag stage1/php $* docker/php
    docker build --rm --tag stage1/symfony2 $* docker/symfony2
    # docker build --rm --tag yuhao $* docker/yuhao
else
    docker build --rm --tag stage1/$1 docker/$1
fi
