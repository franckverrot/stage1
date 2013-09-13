#!/bin/bash

/etc/init.d/mysql start
/etc/init.d/php5-fpm start
/etc/init.d/nginx start
/usr/sbin/sshd

tail -f /var/log/nginx/*.log