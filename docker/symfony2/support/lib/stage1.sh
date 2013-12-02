#!/bin/bash

set -o errexit
set -o pipefail

trap 'exit $?' ERR

STAGE1_CONFIG_PATH=".build.yml"

stage1_announce() {
    echo "------> $@"
}

stage1_get_config_script() {
    test -f "$STAGE1_CONFIG_PATH" && ruby -r yaml -e "puts YAML.load_file('$STAGE1_CONFIG_PATH')['script'] rescue NoMethodError"
}

stage1_get_config_env() {
    test -f "$STAGE1_CONFIG_PATH" && ruby -r yaml -e "puts YAML.load_file('$STAGE1_CONFIG_PATH')['env'] rescue NoMethodError"
}

stage1_exec() {
    # it would be cool to indent all output but right now
    # this only indent stdout (not stderr), so that makes thing
    # more ugly than anything else
    # also, it makes some of composer lines too long for the web term
    "$@" # | sed -ue 's/^/        /'
}

function stage1_websocket_step {
    stage1_websocket_message "build.step" "{ \"step\": \"$1\" }"
}

stage1_websocket_message() {
    true
    # echo "[websocket:$1:$2]"
}