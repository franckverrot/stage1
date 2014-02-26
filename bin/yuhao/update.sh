#!/bin/bash
YUHAO_DIR=/projects/yuhao

cd $YUHAO_DIR
box build
cd - > /dev/null
mv $YUHAO_DIR/yuhao.phar /vagrant/docker/yuhao/support/bin/yuhao.phar

docker build --tag yuhao --rm --no-cache docker/yuhao