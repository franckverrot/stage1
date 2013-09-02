#!/bin/bash

/etc/init.d/mysql start
/etc/init.d/php5-fpm start
/usr/sbin/sshd

nginx -g "daemon off;"