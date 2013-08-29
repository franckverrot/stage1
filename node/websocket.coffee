#!/usr/bin/coffee

Primus              = require 'primus'
{PrimusChannels}    = require './primus-channels.coffee'
http                = require 'http'
amqp                = require 'amqplib'
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

    spark.on 'data', (data) ->
        console.log '<- received ' + data.action.yellow + ' from spark#' + spark.id
        # if data.action == 'build.output.buffer' and buffer.length > 0
        #     console.log '-> sending event "' + 'build.output.buffer'.yellow + '" to spark#' + spark.id
        #     spark.write event: 'build.output.buffer', data: buffer

        if data.action == 'subscribe' and buffer.length > 0
            console.log '-> sending event "' + 'build.output.buffer'.yellow + '" to spark#' + spark.id
            spark.write event: 'build.output.buffer', data: buffer


buffer = []

amqp.connect('amqp://localhost').then (conn) ->
    console.log '[x] amqp connected'
    conn.createChannel().then (channel) ->
        console.log '[x] channel created'
        channel.assertQueue('websockets').then (queue) ->
            console.log '[x] queue created'
            channel.bindQueue(queue.queue, 'amq.fanout', '').then ->
                console.log '[x] queue bound'
                console.log ''
                channel.consume queue.queue, (message) ->
                    content = JSON.parse(message.content.toString('utf-8'))
                    console.log '<- received event "' + content.event + '" for channel "' + content.channel + '"'

                    if content.channel?
                        if content.event == 'build.output'
                            buffer.push content

                        if content.event in ['build.finished', 'build.started']
                            buffer = []

                        if primus.channels[content.channel]?
                            console.log '-> broadcasting event "' + content.event.yellow + '" to channel "' + content.channel.yellow + '"'
                            primus.channels[content.channel].write content
                        else
                            console.log '   channel "' + content.channel.yellow + '" does not exist, skipping'

                    channel.ack message
port = 8090
server.listen port, ->
    console.log '[x] listening on port ' + port