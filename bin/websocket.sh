#!/bin/bash
#docker attach $(docker ps | grep buildapp | awk '{print $1}')

tail -f -n +1 /tmp/stage1-build-output