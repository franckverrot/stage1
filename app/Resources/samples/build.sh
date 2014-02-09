#!/usr/bin/env bash
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
source $DIR/../lib/stage1.sh

# build
stage1_announce running "composer self-update"
stage1_exec "composer self-update"

stage1_announce running "composer install --ansi --no-dev --no-interaction --prefer-dist --no-progress"
stage1_exec "composer install --ansi --no-dev --no-interaction --prefer-dist --no-progress"

