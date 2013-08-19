#!/bin/bash -eu

CONFIG_PATH=${1:-.build.yml}
CONFIG_BEFORE_SCRIPT=""
CONFIG_ENV=""

if [ -f $CONFIG_PATH ]; then
    echo "---> Detected $CONFIG_PATH"
    CONFIG_BEFORE_SCRIPT="$(ruby -r yaml -e "puts YAML.load_file('$CONFIG_PATH')['script'] rescue ''")"
fi

if [ ! -z "$CONFIG_BEFORE_SCRIPT" ]; then
    echo "---> Using $CONFIG_PATH's script commands"

    if [ ! -z "$CONFIG_ENV" ]; then
        echo "---> Using $CONFIG_PATH's env ($CONFIG_ENV)"
        declare $CONFIG_ENV
    fi

    echo "$CONFIG_BEFORE_SCRIPT" | while read CMD; do
        echo "---> Running $CMD"
    done
fi