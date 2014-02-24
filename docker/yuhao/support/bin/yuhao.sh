#!/bin/bash

set -e

if [ -z "$1" -o -z "$2" ]; then
    echo "Usage: $(basename $0) <repo> <ref>"
    exit 1
fi

DEST=/root/repo

git clone --depth 1 --branch $2 $1 $DEST > /dev/null
php /root/yuhao/bin/yuhao --builder /root/YuhaoDefaultBuilder.php $DEST