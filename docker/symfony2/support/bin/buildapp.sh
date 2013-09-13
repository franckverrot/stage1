#!/bin/bash -e

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

source $DIR/../lib/stage1.sh

[ -z "$1" ] && {
    echo "Missing git repository"
    exit 1
}

[ -z "$2" ] && {
    echo "Missing git ref"
    exit 1
}

# [ -z "$3" ] && {
#     echo "Missing git hash"
#     exit 1
# }

[ -z "$4" ] && {
    echo "Github access token is missing"
    exit 1
}

ssh_url=$1
ref=$2
hash=$3
access_token=$4

stage1_announce "starting MySQL server"
stage1_exec /etc/init.d/mysql start

# composer configuration to avoid hitting github's api rate limit
# @todo this has to be moved to the symfony builder
# but it must be ran even when the project provides a custom builder
# so maybe a $builder/bin/before script could be useful
stage1_announce "configuring composer"

stage1_exec mkdir -p /.composer
stage1_exec cat > /.composer/config.json <<EOF
{
    "config": {
        "github-oauth": {
            "github.com": "$access_token"
        }
    }
}
EOF

app_root=/var/www

stage1_announce "cloning repository"
stage1_exec git clone --depth 1 --branch $ref $ssh_url $app_root

cd $app_root

# stage1_exec git reset --hard $hash

builders_root=$(realpath $DIR/../lib/builder)
builders=($builders_root/*)
selected_builder=

if [ -n "$(stage1_get_config_script)" ]; then
    builder="$STAGE1_CONFIG_PATH"
    stage1_announce custom build detected

    stage1_get_config_script | while read cmd; do
        stage1_announce running $cmd
        stage1_exec eval $cmd
    done
else
    for builder in "${builders[@]}"; do
        builder_name=$($builder/bin/detect "$app_root") && selected_builder=$builder && break
    done

    if [ -n "$builder_name" ]; then
        stage1_announce $builder_name app detected
    else
        stage1_announce could not find a builder
        exit 1
    fi

    $builder/bin/build
fi