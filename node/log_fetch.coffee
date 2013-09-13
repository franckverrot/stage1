#!/usr/bin/coffee

Docker = require('dockerode')
colors = require 'colors'
amqp   = require 'amqplib'

console.log '\r\n================================================================================'
console.log 'initiating log reception server'
console.log '================================================================================\r\n'

docker = new Docker socketPath: '/var/run/docker.sock'

colors.setTheme
    verbose: 'cyan'
    info: 'green'
    data: 'grey'
    help: 'cyan'
    warn: 'yellow'
    debug: 'blue'
    error: 'red'

attachOptions =
    logs: true
    stream: true
    stdout: true
    stderr: true

queue = 'websockets'

amqp.connect('amqp://localhost').then (conn) ->
    console.log '[x] amqp connected'
    conn.createChannel().then (channel) ->
        console.log '[x] channel created'
        channel.assertQueue 'websockets'

        docker.listContainers null, (err, containers) ->

            containers.forEach (data) ->
                buildId = data.Image.match(/\/(\d+):/)[1]
                routingKey = 'build.' + buildId

                container = docker.getContainer(data.Id)
                console.log ('attaching to container ' + container.id).info
                container.attach attachOptions, (err, stream) ->
                    stream.on 'data', (line) ->
                        console.log ('got data from ' + container.id).info
                        message =
                            event: 'build.log',
                            channel: routingKey,
                            content: line,
                            timestamp: new Date().getTime()
                            
                        channel.sendToQueue 'websockets', new Buffer(JSON.stringify message, 'utf8')
