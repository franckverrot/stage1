#!/bin/bash

test -n "$STAGE1_DEBUG" && set -x

set -e
set -u

trap 'exit $?' ERR

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
    exit 1
fi

SSH_URL=$1
REF=$2
HASH=$3
ACCESS_TOKEN=$4

echo "---> Starting MySQL server"
/etc/init.d/mysql start

# composer configuration to avoid hitting github's api rate limit
echo "---> Configuring composer"
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

echo "---> Cloning repository"
git clone --depth 1 --branch $REF $SSH_URL $APP_ROOT

cd $APP_ROOT

git reset --hard $HASH

CONFIG_PATH=".build.yml"
CONFIG_BEFORE_SCRIPT=""
CONFIG_ENV=""

if [ -f $CONFIG_PATH ]; then
    echo "---> Detected $CONFIG_PATH"
    CONFIG_BEFORE_SCRIPT="$(ruby -r yaml -e "puts YAML.load_file('$CONFIG_PATH')['script'] rescue NoMethodError")"

    if [ -z "$CONFIG_BEFORE_SCRIPT" ]; then
        echo "---> No script found, continuing with default build"
    fi
fi

if [ -n "$CONFIG_BEFORE_SCRIPT" ]; then
    echo "---> Using $CONFIG_PATH's script commands"

    if [ -n "$CONFIG_ENV" ]; then
        echo "---> Using $CONFIG_PATH's env ($CONFIG_ENV)"
        declare $CONFIG_ENV
    fi

    echo "$CONFIG_BEFORE_SCRIPT" | while read CMD; do
        echo "---> Running $CMD"
        eval $CMD
    done
else
    cp /etc/symfony/parameters.yml.dist app/config/parameters.yml

    echo "---> Installing dependencies through composer"
    composer install --ansi --no-progress --no-dev --prefer-dist --no-interaction

    if app/console list doctrine:database > /dev/null 2>&1; then
        echo "---> Initializing database"
        app/console doctrine:database:create
        app/console doctrine:schema:update --force
    fi

    if app/console list doctrine:fixtures > /dev/null 2>&1; then
        echo "---> Loading fixtures"
        app/console doctrine:fixtures:load
    fi
fi

chmod -R 777 app/cache app/logs