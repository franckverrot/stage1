#!/bin/bash
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
        # eval $CMD
    done
fi