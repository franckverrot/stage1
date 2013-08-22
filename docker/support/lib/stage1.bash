#!/bin/bash

set -o errexit
set -o pipefail

trap 'exit $?' ERR

STAGE1_CONFIG_PATH=".build.yml"

stage1_announce() {
    echo "------> $@"
}

stage1_get_config_script() {
    test -f "$@" && ruby -r yaml -e "puts YAML.load_file('$@')['script'] rescue NoMethodError"
}

stage1_get_config_env() {
    test -f "$@" && ruby -r yaml -e "puts YAML.load_file('$@')['env'] rescue NoMethodError"
}

stage1_exec() {
    # it would be cool to indent all output but right now
    # this only indent stdout (not stderr), so that makes thing
    # more ugly than anything else
    # also, it makes some of composer lines too long for the web term
    "$@" # | sed -ue 's/^/        /'
}