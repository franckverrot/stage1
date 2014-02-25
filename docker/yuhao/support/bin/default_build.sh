#!/bin/bash -e

if [ ! -z "$DEBUG" ]; then
    set -x
fi

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
APP_ROOT=/var/www

source /usr/local/lib/stage1.sh

cd $APP_ROOT

# stage1_exec git reset --hard $hash

BUILDERS_ROOT=/usr/local/lib/builder
BUILDERS=($BUILDERS_ROOT/*)
SELECTED_BUILDER=

stage1_websocket_step "select_builder"

if [ -n "$(stage1_get_config_build)" ]; then
    BUILDER="$STAGE1_CONFIG_PATH"
    # stage1_announce "custom build detected"

    stage1_get_config_build | while read cmd; do
        stage1_announce running "$cmd"
        stage1_exec "$cmd"
    done
else
    for BUILDER in "${BUILDERS[@]}"; do
        BUILDER_NAME=$($BUILDER/bin/detect "$APP_ROOT") && SELECTED_BUILDER=$BUILDER && break
    done

    if [ -n "$BUILDER_NAME" ]; then
        stage1_announce "using \"$BUILDER_NAME\" builder"
    else
        stage1_announce "could not find a builder"
        exit 1
    fi

    $BUILDER/bin/build
fi

# build common

if [ -n "$(stage1_get_config_writable)" ]; then
    stage1_announce "configuring writable files and folders"

    stage1_get_config_writable | while read writable; do
        if [ ! -f $writable -a ! -d $writable ]; then
            stage1_announce file or folder '"'$writable'"' not found
        else
            stage1_announce setting permissions on '"'$writable'"'
            stage1_exec "chmod -R 777 $writable"
        fi
    done
fi