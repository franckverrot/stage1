#!/bin/bash

set -o errexit
set -o pipefail

if [ ! -z "$DEBUG" ]; then
    set -x
fi

trap 'exit $?' ERR

STAGE1_CONFIG_PATH=".stage1.yml"

stage1_announce() {
    # echo -e "\033[33mstage1\033[0m> $@"
    echo -e "  $@"
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
    echo -e "\$ $@"
    (bash -c "$@")
}

stage1_exec_bg() {
    echo -e "\$ $@"
    (bash -c "$@" &)
}

stage1_composer_configure() {
    if [ ! -d /.composer ]; then
        mkdir -p /.composer
    fi
    
    cat > /.composer/config.json <<EOF
{
    "config": {
        "github-oauth": {
            "github.com": "$1"
        }
    }
}
EOF
}
