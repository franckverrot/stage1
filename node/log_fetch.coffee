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

# by setting logs to false we risk losing the first second of log
# but we avoid duplicating logs if for any reason the log_fetcher
# daemon restarts. worth it imho.
attachOptions =
    logs: false
    stream: true
    stdout: true
    stderr: true

queues = ['websockets', 'build_log']
pollInterval = 1000
filterCommand = /^runapp/

# list of already attached containers
attached = []

# containers that could should not be attached because
# the image name could not be parsed into something we know
skipped = []

attach_containers = (docker, channel) ->
    docker.listContainers null, (err, containers) ->

        containers.forEach (data) ->
            unless attached.indexOf(data.Id) == -1
                return

            unless skipped.indexOf(data.Id) == -1
                return

            unless data.Command.match filterCommand
                return

            match = data.Image.match(/\/(\d+):/)

            unless match
                console.log ('could not attach ' + data.Id).error
                console.log data
                skipped.push data.Id
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
                        build_id: buildId,
                        content: line,
                        timestamp: new Date().getTime()
                    
                    buffer = new Buffer(JSON.stringify message, 'utf8')

                    queues.forEach (queue) ->
                        channel.sendToQueue queue, buffer

amqp.connect('amqp://localhost').then (conn) ->
    console.log '[x] amqp connected'
    conn.createChannel().then (channel) ->
        console.log '[x] channel created'
        queues.forEach (queue) ->
            channel.assertQueue queue

        # @todo there is no need to poll! http://docs.docker.io/en/latest/api/docker_remote_api_v1.6/#get--events
        (monitor = ->
            attach_containers docker, channel
            setTimeout monitor, pollInterval)()
