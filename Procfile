consumer-build: app/console rabbitmq:consumer -vvv -m 1 -w build
consumer-kill: app/console rabbitmq:consumer -vvv -m 1 -w kill
consumer-project_import: app/console rabbitmq:consumer -vvv -m 1 -w project_import
hipache: hipache --config app/config/hipache_$STAGE1_ENV.json
log_fetch: coffee node/log_fetch.coffee
websockets: coffee node/websocket.coffee