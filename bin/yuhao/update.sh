#!/bin/bash -xe

YUHAO_DIR=/projects/yuhao

if [ -d $YUHAO_DIR ]; then
    cd $YUHAO_DIR
    bin/box build
    cd - > /dev/null
    mv $YUHAO_DIR/yuhao.phar /vagrant/docker/yuhao/support/bin/yuhao.phar
fi;

docker build --tag stage1/yuhao --rm --no-cache docker/yuhao
