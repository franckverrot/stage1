#!/bin/bash
export DEBIAN_FRONTEND=noninteractive

# can't use a 3.8 kernel on a 12.04 ubuntu on digital ocean
# see https://github.com/dotcloud/docker/issues/1017
apt-get install linux-image-extra-3.2.0-23-virtual

echo 'export SYMFONY_ENV=prod' >> /etc/environment