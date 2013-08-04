#!/bin/bash
export DEBIAN_FRONTEND=noninteractive

apt-get -qy install \
    build-essential \
    python2.7-dev \
    python-pip

pip install httpie
pip install fabric

rabbitmq-plugins enable rabbitmq_management