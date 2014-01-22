#!/bin/bash -e

if [ ! -z "$DEBUG" ]; then
    set -x
fi

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

source $DIR/../lib/stage1.sh

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