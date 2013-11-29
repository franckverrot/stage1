#!/bin/bash

export DEBIAN_FRONTEND=noninteractive

# sleep 1d

echo LANG=en_US.UTF-8 > /etc/default/locale
rm -f /var/lib/locales/supported.d/*
echo en_US.UTF-8 UTF-8 > /var/lib/locales/supported.d/locale

export LANG=en_US.UTF-8

locale-gen

apt-get install -qy vim git curl

cp /tmp/apt-sources.list /etc/apt/sources.list

cp /tmp/apt-rabbitmq.list /etc/apt/sources.list.d/rabbitmq.list
curl http://www.rabbitmq.com/rabbitmq-signing-key-public.asc | apt-key add -

cp /tmp/apt-dotdeb.list /etc/apt/sources.list.d/dotdeb.list
curl http://www.dotdeb.org/dotdeb.gpg | apt-key add -

cp /tmp/apt-docker.list /etc/apt/sources.list.d/docker.list
curl http://get.docker.io/gpg | apt-key add -

apt-get update

apt-get install -qy python-software-properties

add-apt-repository -y ppa:chris-lea/node.js

apt-get update

apt-get -qy install \
    nginx \
    php5-fpm \
    php5-cli \
    php5-mysqlnd \
    php5-redis \
    php5-curl \
    redis-server \
    rabbitmq-server \
    mysql-client \
    mysql-server \
    amqp-tools \
    realpath \
    htop \
    acl \
    nodejs \
    lxc-docker \
    linux-image-generic-lts-raring

# enable ACL on /

sed -e 's/errors=remount-ro/&,acl/' -i /etc/fstab
mount -o remount /

# install configuration
cp /tmp/nginx-default /etc/nginx/sites-available/default

cp /tmp/docker-default /etc/default/docker

cp /tmp/php-php.ini /etc/php5/cli/php.ini
cp /tmp/php-php.ini /etc/php5/fpm/php.ini

if [ -f /tmp/rabbitmq-rabbitmq.config ]; then
    cp /tmp/rabbitmq-rabbitmq.config /etc/rabbitmq/rabbitmq.config
fi

if [ -f /tmp/grub-default ]; then
    mv /tmp/grub-default /etc/default/grub
    update-grub
fi

# install coffeescript
npm install -g coffee-script

# install composer

curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

# docker specific stuff
docker pull ubuntu:precise

# hipache
npm install -g git://github.com/ubermuda/hipache.git
mkdir -p /var/log/hipache