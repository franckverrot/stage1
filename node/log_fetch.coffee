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
pollInterval = 1000
attached = []

attach_containers = (docker, channel) ->
    docker.listContainers null, (err, containers) ->

        containers.forEach (data) ->
            unless attached.indexOf(data.Id) == -1
                return

            unless data.Command.match /^runapp/
                return

            match = data.Image.match(/\/(\d+):/)

            unless match
                console.log ('could not attach ' + data.Id).error
                console.log data
                return

            buildId = match[1]
            routingKey = 'build.' + buildId

            container = docker.getContainer(data.Id)
            console.log ('attaching container ' + container.id).info
            attached.push data.Id
            container.attach attachOptions, (err, stream) ->
                unless stream
                    console.log ('failed attaching container ' + container.id).error
                    return

                stream.on 'data', (line) ->
                    console.log ('got data from ' + container.id).info
                    message =
                        event: 'build.log',
                        channel: routingKey,
                        content: line,
                        timestamp: new Date().getTime()
                        
                    channel.sendToQueue 'websockets', new Buffer(JSON.stringify message, 'utf8')

amqp.connect('amqp://localhost').then (conn) ->
    console.log '[x] amqp connected'
    conn.createChannel().then (channel) ->
        console.log '[x] channel created'
        channel.assertQueue 'websockets'

        (monitor = ->
            attach_containers docker, channel
            setTimeout monitor, pollInterval)()
