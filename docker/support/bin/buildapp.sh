#!/bin/bash

test -n "$STAGE1_DEBUG" && set -x

set -e

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

TRAVIS_PATH=.travis.yml

if [ -f $TRAVIS_PATH ]; then
    echo "---> Detected $TRAVIS_PATH, checking before script"

    TRAVIS_BEFORE_SCRIPT="$(ruby -r yaml -e "puts YAML.load_file('$TRAVIS_PATH')['before_script'] rescue NoMethodError")"
    TRAVIS_ENV="$(ruby -r yaml -e "puts YAML.load_file('$TRAVIS_PATH')['env'][0] rescue NoMethodError")"
fi

if [ ! -z "$TRAVIS_BEFORE_SCRIPT" ]; then
    echo "---> Using $TRAVIS_PATH's before_script commands"

    if [ ! -z "$TRAVIS_ENV" ]; then
        echo "---> Using $TRAVIS_PATH's env ($TRAVIS_ENV)"
        declare $TRAVIS_ENV
    fi

    echo "$TRAVIS_BEFORE_SCRIPT" | while read CMD; do
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