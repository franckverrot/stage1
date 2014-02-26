#!/bin/bash
YUHAO_DIR=/projects/yuhao

if [ -d $YUHAO_DIR ]; then
    cd $YUHAO_DIR
    box build
    cd - > /dev/null
    mv $YUHAO_DIR/yuhao.phar /vagrant/docker/yuhao/support/bin/yuhao.phar
fi;

docker build --tag yuhao --rm --no-cache docker/yuhao