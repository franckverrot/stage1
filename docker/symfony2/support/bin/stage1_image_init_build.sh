#!/bin/bash

source /usr/local/lib/stage1.sh

/etc/init.d/mysql start 2>&1 > /dev/null

RET=1
while [[ RET -ne 0 ]]; do
    sleep 1
    mysql -e 'exit'; RET=$?
done

mysqladmin -u root create symfony