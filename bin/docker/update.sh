#!/bin/bash -ex

if [ -z "$1" -o ! -d "$1" ]; then
    docker build --rm --tag stage1 docker/stage1
    docker build --rm --tag php docker/php
    docker build --rm --tag symfony2 docker/symfony2
    docker build --rm --tag yuhao docker/yuhao
else
    docker build --rm --tag $1 docker/$1
fi
