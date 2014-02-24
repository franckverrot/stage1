#!/bin/bash

#!/bin/bash -e

if [ ! -z "$DEBUG" ]; then
    set -x
fi

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

source $DIR/../lib/stage1.sh

# stage1_announce "starting MySQL server"
stage1_exec "/etc/init.d/mysql start 2>&1 > /dev/null"

# composer configuration to avoid hitting github's api rate limit
# @todo this has to be moved to the symfony builder
# but it must be ran even when the project provides a custom builder
# so maybe a $builder/bin/before script could be useful
stage1_announce "configuring composer with token $ACCESS_TOKEN"

stage1_exec "mkdir -p /.composer"
stage1_exec "$(cat <<EXEC
cat > /.composer/config.json <<EOF
{
    "config": {
        "github-oauth": {
            "github.com": "$ACCESS_TOKEN"
        }
    }
}
EOF
EXEC
)"

APP_ROOT=/var/www

if [ -d $APP_ROOT ]; then
    rm -rf $APP_ROOT
fi

stage1_announce "cloning repository $ssh_url"
stage1_websocket_step "clone_repository"
stage1_exec "git clone --quiet --depth 1 --branch $REF $SSH_URL $APP_ROOT"

stage1_announce 'running build script'

cd $APP_ROOT
/usr/local/bin/yuhao_build