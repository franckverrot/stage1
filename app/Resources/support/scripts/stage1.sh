#!/bin/bash
export DEBIAN_FRONTEND=noninteractive

apt-get install -qy vim git curl

cp /tmp/apt-sources.list /etc/apt/sources.list

cp /tmp/apt-rabbitmq.list /etc/apt/sources.list.d/rabbitmq.list
curl http://www.rabbitmq.com/rabbitmq-signing-key-public.asc | apt-key add -

cp /tmp/apt-dotdeb.list /etc/apt/sources.list.d/dotdeb.list
curl http://www.dotdeb.org/dotdeb.gpg | apt-key add -

apt-get update

apt-get -qy install \
    nginx \
    php5-fpm \
    php5-cli \
    php5-mysqlnd \
    php5-redis \
    redis-server \
    rabbitmq-server \
    mysql-client \
    mysql-server \
    monit \
    amqp-tools \
    realpath \
    htop

cp /tmp/nginx-default /etc/nginx/sites-available/default

cp /tmp/php-php.ini /etc/php5/cli/php.ini
cp /tmp/php-php.ini /etc/php5/fpm/php.ini

if [ -f /tmp/rabbitmq-rabbitmq.config ]; then
    cp /tmp/rabbitmq-rabbitmq.config /etc/rabbitmq/rabbitmq.config
fi

cp /tmp/monit-monitrc /etc/monit/monitrc
cp /tmp/monit-consumer-build /etc/monit/conf.d/consumer-build
cp /tmp/monit-consumer-kill /etc/monit/conf.d/consumer-kill
cp /tmp/monit-websocket-build /etc/monit/conf.d/websocket-build
cp /tmp/monit-websocket-build-output /etc/monit/conf.d/websocket-build-output

curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

mkdir /tmp/run
chown vagrant /tmp/run

# docker specific stuff

apt-get install -qy \
    linux-image-generic-lts-raring \
    python-software-properties
add-apt-repository -y ppa:dotcloud/lxc-docker
apt-get update
apt-get install -qy lxc-docker