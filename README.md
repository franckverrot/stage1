stage1
======

services
--------

    bin/consumer start build
    bin/consumer start kill

    bin/websocketd --port=8888 bin/websocket/build-output
    bin/websocketd --port=8889 bin/websocket/build