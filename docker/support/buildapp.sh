#!/bin/bash

# small delay to be sure to fetch entire output when building projects
# sleep 1;

if [ -z "$1" ]; then
    echo "Missing git repository"
    exit 1
fi

if [ -z "$2" ]; then
    echo "Github access token is missing"
    exit 2
fi

# composer configuration to avoid hitting github's api rate limit
mkdir /.composer
cat > /.composer/config.json <<EOF
{
    "config": {
        "github-oauth": {
            "github.com": "$2"
        }
    }
}
EOF

APP_ROOT=/var/www

git clone --depth 1 $1 $APP_ROOT

cd $APP_ROOT

cp /etc/symfony/parameters.yml.dist app/config/parameters.yml
composer install --ansi --no-progress --no-dev --prefer-dist
chmod -R 777 app/cache app/logs