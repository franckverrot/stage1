#!/usr/bin/coffee

opts =
    docker_socket: process.env.ALDIS_DOCKER || '/var/run/docker.sock'
    amqp_dsn: process.env.ALDIS_AMQP || 'amqp://localhost'
    queue: process.env.ALDIS_QUEUE || 'docker_output'

attachOptions = { logs: false, stream: true, stdout: true, stderr: true }

Docker = require('dockerode')
amqp   = require('amqplib')
colors = require('colors')
domain = require('domain')

console.log('.. initializing aldis')

amqp.connect(opts.amqp_dsn).then((conn) ->
    return conn.createChannel()
).then((channel) ->
    channel.assertQueue(opts.queue)
    console.log((' ✓ connected to amqp  at "' + opts.amqp_dsn + '/' + opts.queue + '"').green)

    docker = new Docker({ socketPath: opts.docker_socket })
    docker.getEvents(null, (err, stream) ->
        console.log((' ✓ connected to docker at "' + opts.docker_socket + ':/getEvents').green)

        stream.on('data', (data) ->
            data = JSON.parse(data)
            console.log('<- got "' + data.status + '" for container "' + data.id.yellow + '"')

            if data.status != 'create'
                return

            domain.create().on('error', (err) ->
                # most of the time it's dockerode replaying the callback when the connection is reset
                # see dockerode/lib/modem.js:87
                throw err unless err.code == 'ECONNRESET'
            ).run(->
                container = docker.getContainer(data.id)
                console.log('.. trying to attach container "' + data.id.yellow + '"')
                container.attach(attachOptions, (err, stream) ->
                    throw err if err

                    stream.on('end', -> console.log '   stream ended for container "' + container.id.yellow + '"')

                    stream.on('data', (line) ->
                        console.log('<- got ' + line.length + ' bytes from container "' + container.id.yellow + '"')
                        message = { container: container.id, line: line }
                        buffer = new Buffer(JSON.stringify(message), 'utf8')

                        channel.sendToQueue(opts.queue, buffer)
                        console.log('-> sent ' + buffer.length + ' bytes to "' + opts.queue + '"')
                    )
                )
            )
        )
    )
)