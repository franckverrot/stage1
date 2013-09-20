#!/bin/bash

function init {
    /etc/init.d/mysql start
    /etc/init.d/php5-fpm start
    /etc/init.d/nginx start
    /usr/sbin/sshd
}

init 2>&1 > /dev/null

tail -f /var/log/nginx/*.log /var/www/app/logs/*.log /var/log/php5-fpm.log