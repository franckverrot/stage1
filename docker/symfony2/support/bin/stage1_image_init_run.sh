#!/bin/bash

source /usr/local/lib/stage1.sh

# try to detect the front controller

declare -a files=(index.php app.php)

for file in ${files[@]}; do
    if [ -f ./web/$file ]; then
        sed -e "s/%frontcontroller%/$file/" -i /etc/nginx/sites-enabled/default
        break;
    fi
done;

# start services

declare -a services=(mysql php5-fpm nginx)

for service in ${services[@]}; do
    /etc/init.d/$service start 2>&1 > /dev/null
done;