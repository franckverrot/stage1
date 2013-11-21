#!/bin/bash

export DEBIAN_FRONTEND=noninteractive

echo "STAGE1_ENV=prod" >> /etc/environment
echo "SYMFONY_ENV=prod" >> /etc/environment