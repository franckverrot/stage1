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

# @todo this should not be necessary
mkdir -p $APP_ROOT/app/logs/
touch $APP_ROOT/app/logs/prod.log /var/log/php.log

chmod -R 777 $APP_ROOT/app/logs $APP_ROOT/app/cache