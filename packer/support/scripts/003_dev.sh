#!/bin/bash

export DEBIAN_FRONTEND=noninteractive

usermod -aG docker vagrant

cp /etc/ssh/ssh_config /home/vagrant/.ssh/config
chmod 0600 /home/vagrant/.ssh/config

if [ -f /tmp/grub-default ]; then
    mv /tmp/grub-default /etc/default/grub
    update-grub
fi

redis-cli RPUSH frontend:stage1.dev stage1 http://127.0.0.1:8080/

apt-get install -q -y tcpflow socat

apt-get -qy install \
    build-essential \
    python2.7-dev \
    python-pip

pip install httpie
pip install fabric

rabbitmq-plugins enable rabbitmq_management

apt-get install -q -y ruby rubygems
gem install --no-ri --no-rdoc bundler

echo "STAGE1_ENV=dev" >> /etc/environment
echo "SYMFONY_ENV=dev" >> /etc/environment