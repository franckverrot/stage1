#!/usr/bin/coffee

Primus              = require 'primus'
{PrimusChannels}    = require './primus-channels.coffee'
http                = require 'http'
amqp                = require 'amqp'
colors              = require 'colors'

console.log '\r\n================================================================================'
console.log 'initiating websockets server'
console.log '================================================================================\r\n'

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
    privatePattern: /(project|user)\.\d+/

primus = new Primus(server, options)
primus.use PrimusChannels

primus.save(__dirname + '/../web/js/primus.js')

primus.on 'connection', (spark) ->
    console.log ('<- spark#' + spark.id + ' connected').info

    spark.on 'subscribed', (channel) ->
        if buffer[channel.name] and buffer[channel.name].length > 0
            console.log '-> sending event "' + 'build.output.buffer'.yellow + '" for channel "' + channel.name.yellow + '" to spark#' + spark.id
            console.log '   buffer contains ' + (new String(buffer[channel.name].length).yellow) + ' items'
            
            spark.write event: 'build.output.buffer', channel: channel.name, timestamp: null, data: buffer[channel.name]

    spark.on 'data', (data) ->

        if data.action == 'subscribe'
            console.log '<- received ' + data.action.yellow + ' on channel ' + data.channel.yellow + ' from spark#' + spark.id
        else
            console.log '<- received ' + data.action.yellow + ' from spark#' + spark.id

buffer = {}

opts =
    amqp_host: 'localhost'
    queue: 'websockets'
    exchanges: { 'websockets': 'direct', 'aldis': 'fanout' }

amqp.createConnection { host: opts.amqp_host }, { reconnect: false }, (conn) ->
    console.log((' ✓ connected to amqp at "' + opts.amqp_host + '"').green)
    conn.queue opts.queue, (queue) ->
        console.log((' ✓ queue ' + opts.queue + ' is usable').green)

        for exchange, type of opts.exchanges
            console.log('.. connecting to exchange ' + exchange.yellow + ' (type: ' + type.yellow + ')')
            conn.exchange exchange, { type: type }, (exchange) ->
                console.log((' ✓ connected to exchange ' + exchange.name).green)
                queue.bind exchange, '', (e) ->
                    console.log((' ✓ queue ' + queue.name + ' bound to exchange ' + e.name).green)

        queue.subscribe (message, headers, deliveryInfo) ->

            if message.contentType?
                message = JSON.parse(message.data)

            #
            # expected message content format, one of:
            #
            # default node-amqp message format
            #
            # { data: <Buffer>
            #   contentType: <some content type>}
            #
            # standard stage1 format
            # 
            # { event: <string>,
            #   channel: <string>,
            #   data: <some mixed data> }
            #
            # build log fragment from aldis
            #
            # { container: <a docker container id>,
            #   timestamp: <microseconds timestmap>,
            #   type: <stream type id>,
            #   length: <content length>,
            #   content: <actual message>,
            #   env: { CHANNEL: <websocket channel>, BUILD_ID: <stage1 build id> } }
            #
            # everything *must* be converted to the standard stage1 format
            #

            if message.container?
                message =
                    event: 'build.log',
                    channel: message.env.CHANNEL,
                    data:
                        build:
                            id: message.env.BUILD_ID
                        length: message.length
                        message: message.content
                        type: 'output',
                        stream: message.type

            if not message.channel?
                return

            console.log('<- event ' + message.event.yellow + ' for channel ' + message.channel.yellow)

            if message.event in ['build.finished', 'build.started']
                console.log('   cleaning buffer for channel ' + message.channel.yellow)
                delete buffer[message.channel] if buffer[message.channel]
            else
                buffer[message.channel] = [] unless buffer[message.channel]
                buffer[message.channel].push(message)

            if primus.channels[message.channel]?
                primus.channels[message.channel].write(message)

port = 8090
server.listen port, ->
    console.log '[x] listening on port ' + port