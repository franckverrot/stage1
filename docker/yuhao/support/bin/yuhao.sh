#!/bin/bash -e

if [ -z "$1" ]; then
    echo "Usage $0 <git repository>"
    exit 1
fi

git clone $1 /var/www/ > /dev/null
php /root/yuhao/bin/yuhao --ansi --builder /root/YuhaoDefaultBuilder.php /var/www