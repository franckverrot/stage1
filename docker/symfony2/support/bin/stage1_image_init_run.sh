#!/bin/bash

source /usr/local/lib/stage1.sh

# try to detect the front controller

declare -a files=(index.php app.php)

for file in ${files[@]}; do
    if [ -f ./web/$file ]; then
        sed -e "s,%app_front_controller%,$file," -i /etc/nginx/sites-enabled/default
        break;
    fi
done;

sed -e "s,%app_root%,$APP_ROOT," -i /etc/nginx/sites-enabled/default

# start services

declare -a services=(mysql php5-fpm nginx)

for service in ${services[@]}; do
    /etc/init.d/$service start 2>&1 > /dev/null
done;

LOG_FILES="/var/log/nginx/*.log $APP_ROOT/app/logs/*.log /var/log/php5-fpm.log"