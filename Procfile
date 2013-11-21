consumer-build: app/console rabbitmq:consumer -vvv -m 1 -w build
consumer-kill: app/console rabbitmq:consumer -vvv -m 1 -w kill
consumer-project-import: app/console rabbitmq:consumer -vvv -m 1 -w project_import

hipache: hipache --config app/config/hipache_$STAGE1_ENV.json
websockets: coffee node/websocket.coffee
log-fetch: coffee node/log_fetch.coffee