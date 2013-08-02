#!/bin/bash

# small delay to be sure to fetch entire output when building projects
# sleep 1;

if [ -z "$1" ]; then
    echo "Missing git repository"
    exit 1
fi

if [ -z "$2" ]; then
    echo "Missing git ref"
    exit 1
fi

if [ -z "$3" ]; then
    echo "Missing git hash"
    exit 1
fi

if [ -z "$4" ]; then
    echo "Github access token is missing"
    exit 2
fi

function fail {
    RES=$?
    echo "$@"
    exit $RES
}

CLONE_URL=$1
REF=$2
HASH=$3
ACCESS_TOKEN=$4

# composer configuration to avoid hitting github's api rate limit
mkdir /.composer
cat > /.composer/config.json <<EOF
{
    "config": {
        "github-oauth": {
            "github.com": "$ACCESS_TOKEN"
        }
    }
}
EOF

APP_ROOT=/var/www

git clone --depth 1 --branch $REF $CLONE_URL $APP_ROOT

cd $APP_ROOT

git reset --hard $HASH

cp /etc/symfony/parameters.yml.dist app/config/parameters.yml
composer install --ansi --no-progress --no-dev --prefer-dist
chmod -R 777 app/cache app/logs