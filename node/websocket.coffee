#!/usr/bin/coffee

require 'coffee-script'

Primus              = require 'primus'
{PrimusChannels}    = require './primus-channels.coffee'
http                = require 'http'
amqp                = require 'amqp'
colors              = require 'colors'

colors.setTheme
    verbose: 'cyan'
    info: 'green'
    data: 'grey'
    help: 'cyan'
    warn: 'yellow'
    debug: 'blue'
    error: 'red'

server = http.createServer (req, res) ->
    res.writeHead 500
    res.end 'Not implemented\n'

options =
    transformer: 'websockets'
    secret: 'ThisIsNotSoSecret'
    auth_url: '/primus/auth'

primus = new Primus(server, options)
primus.use PrimusChannels

primus.save(__dirname + '/../web/js/primus.js')

primus.on 'connection', (spark) ->
    console.log ('spark#' + spark.id + ' connected').yellow

port = 8090
server.listen port, ->
    console.log ('listening on port ' + port).info

    conn = amqp.createConnection { host: 'localhost' }, { defaultExchangeName: 'amq.fanout' }
    conn.on 'ready', ->
        conn.queue 'websockets', (queue) ->
            queue.bind 'amq.fanout', '#'
            queue.subscribe (message, headers, deliveryInfo) ->
                primus.write JSON.parse(message.data.toString('utf-8'))