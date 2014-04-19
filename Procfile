consumer-build: app/console rabbitmq:consumer -vvv -m 1 -w build
consumer-kill: app/console rabbitmq:consumer -vvv -m 1 -w kill
consumer-stop: app/console rabbitmq:consumer -vvv -m 1 -w stop
consumer-project-import: app/console rabbitmq:consumer -vvv -m 1 -w project_import
consumer-docker-output: app/console rabbitmq:consumer -vvv -w -m 100 docker_output

hipache: hipache --config app/config/hipache_$STAGE1_ENV.json
websockets: coffee node/websocket.coffee
aldis: node node/node_modules/aldis/aldis.js -A -E CHANNEL -E BUILD_ID -l -a batcave.stage1.io