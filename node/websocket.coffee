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

    spark.on 'subscribed', (channel) ->
        if buffer[channel.name] and buffer[channel.name].length > 0
            console.log '-> sending event "' + 'build.output.buffer'.yellow + '" to spark#' + spark.id
            spark.write event: 'build.output.buffer', data: buffer[channel.name]

    spark.on 'data', (data) ->

        if data.action == 'subscribe'
            console.log '<- received ' + data.action.yellow + ' on channel ' + data.channel.yellow + ' from spark#' + spark.id
        else
            console.log '<- received ' + data.action.yellow + ' from spark#' + spark.id

buffer = {}

amqp.connect('amqp://localhost').then (conn) ->
    console.log '[x] amqp connected'
    conn.createChannel().then (channel) ->
        console.log '[x] channel created'
        channel.assertQueue('websockets').then (queue) ->
            console.log '[x] queue created'
            channel.bindQueue(queue.queue, 'amq.fanout', '').then ->
                console.log '[x] queue bound'
                console.log ''
                # expected message content format:
                # { event: <string>,
                #   channel: <string> }
                channel.consume queue.queue, (message) ->
                    content = JSON.parse(message.content.toString('utf-8'))
                    console.log '<- received event "' + content.event + '" for channel "' + content.channel + '"'

                    if content.channel?
                        unless buffer[content.channel]
                            buffer[content.channel] = []

                        buffer[content.channel].push(content)

                        if content.event == 'build.finished' and buffer[content.channel]
                            console.log 'cleaning mess in channel ' + content.channel.yellow
                            for m, i in buffer[content.channel]
                                if !m or (m.data and m.data.build.id == content.data.build.id)
                                    buffer[content.channel].splice(i, 1)

                        if primus.channels[content.channel]?
                            console.log '-> broadcasting event "' + content.event.yellow + '" to channel "' + content.channel.yellow + '"'
                            primus.channels[content.channel].write content
                        else
                            console.log '   channel "' + content.channel.yellow + '" does not exist, skipping'

                    channel.ack message
port = 8090
server.listen port, ->
    console.log '[x] listening on port ' + port