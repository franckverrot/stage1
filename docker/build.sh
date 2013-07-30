#!/bin/bash
git clone $1 /vagrant
cd /vagrant
cp /etc/symfony/parameters.yml.dist app/config/parameters.yml
composer install
/etc/init.d/nginx start
/etc/init.d/php5-fpm start
chmod -R 777 app/cache app/logs
sleep 1d