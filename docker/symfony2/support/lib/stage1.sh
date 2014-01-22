#!/bin/bash

set -o errexit
set -o pipefail

trap 'exit $?' ERR

STAGE1_CONFIG_PATH=".build.yml"

stage1_announce() {
    echo -e "\033[33mstage1\033[0m> $@"
}

stage1_get_config_key() {
    test -f "$1" && ruby -r yaml -e "puts YAML.load_file('$1')['$2'] rescue NoMethodError"
}

stage1_get_config_writable() {
    stage1_get_config_key $STAGE1_CONFIG_PATH "writable"
}

stage1_get_config_build() {
    stage1_get_config_key $STAGE1_CONFIG_PATH "build"
}

stage1_get_config_env() {
    stage1_get_config_key $STAGE1_CONFIG_PATH "env"
}

stage1_get_config_run() {
    stage1_get_config_key $STAGE1_CONFIG_PATH "run"
}

stage1_exec() {
    (bash -c "$@")
}

stage1_exec_bg() {
    (bash -c "$@" &)
}

function stage1_websocket_step {
    stage1_websocket_message "build.step" "{ \"step\": \"$1\" }"
}

stage1_websocket_message() {
    true
    # echo "[websocket:$1:$2]"
}