#!/bin/bash
export DEBIAN_FRONTEND=noninteractive

redis-cli RPUSH frontend:stage1 stage1 http://127.0.0.1:8080/

apt-get -qy install \
    build-essential \
    python2.7-dev \
    python-pip

pip install httpie
pip install fabric

rabbitmq-plugins enable rabbitmq_management

# phantomjs + casperjs

apt-get install -yq \
    libfontconfig1

wget https://phantomjs.googlecode.com/files/phantomjs-1.9.1-linux-x86_64.tar.bz2 -O- | tar xzm
wget https://github.com/n1k0/casperjs/tarball/master -O- | tar xzm

(cd *phantomjs*; ln -sf $(pwd)/bin/phantomjs /usr/local/bin/phantomjs)
(cd *casperjs*; ln -s $(pwd)/bin/casperjs /usr/local/bin/casperjs)