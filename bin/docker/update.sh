#!/bin/bash -ex

if [ -z "$1" ]; then
    docker build -t stage1 docker/stage1
    docker build -t php docker/php
    docker build -t symfony2 docker/symfony2
else
    docker build -t $1 docker/$1
fi