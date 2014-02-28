#!/bin/bash

SOCK=/var/run/docker.sock

mv $SOCK $SOCK.original
socat -t100 -x -v UNIX-LISTEN:$SOCK,mode=777,reuseaddr,fork UNIX-CONNECT:$SOCK.original
mv $SOCK.original $SOCK