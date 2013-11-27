#!/usr/bin/coffee

VERSION = '0.0.1'

getopt = require('node-getopt').create([
    ['d', 'docker=ARG', 'Docker DSN (eg: /var/run/docker.sock or 127.0.0.1:4243)'],
    ['a', 'amqp=ARG', 'AMQP DSN (eg: localhost)'],
    ['q', 'queue=ARG', 'queue to pipe messages to'],
    ['h', 'help', 'show this help']
    ['A', 'attach', 'attach already running containers']
    ['m', 'multiplex', 'force multiplexing']
    ['n', 'no-multiplex', 'force no multiplexing'],
    ['l', 'log', 'output logs as they arrive']
])
.bindHelp()
.parseSystem()

opts =
    docker: getopt.options.docker || '/var/run/docker.sock'
    amqp:   getopt.options.amqp   || 'localhost'
    queue:  getopt.options.queue  || 'docker_output'

Docker = require('dockerode')
amqp   = require('amqplib')
colors = require('colors')
domain = require('domain')
util   = require('util')

console.log('.. initializing aldis')
console.log('   hit ^C to quit')

if opts.docker.indexOf(':') != -1
    dockerOpts = opts.docker.split(':')
    dockerOpts = { host: docker[0], port: docker[1] }
else
    dockerOpts = { socketPath: opts.docker }


docker = new Docker(dockerOpts)

amqp.connect('amqp://' + opts.amqp).then((conn) ->
    return conn.createChannel()
).then((channel) ->
    channel.assertQueue(opts.queue)
    console.log((' ✓ connected to amqp   at "' + opts.amqp + '/' + opts.queue + '"').green)

    if getopt.options.attach
        console.log('.. attaching already running containers')
        docker.listContainers null, (err, containers) ->
            containers.forEach (data) ->
                attach(docker.getContainer(data.Id), channel)

    docker.getEvents(null, (err, stream) ->
        console.log((' ✓ connected to docker at "' + opts.docker + '"').green)

        stream.on('data', (data) ->
            data = JSON.parse(data)
            # console.log('<- got "' + data.status + '" for container "' + data.id.yellow + '"')

            if data.status != 'create'
                return

            attach(docker.getContainer(data.id), channel)
        )
    )
)

# @see http://docs.docker.io/en/master/api/docker_remote_api_v1.7/#attach-to-a-container
parse_with_multiplexing = (line) ->
    buf = new Buffer(line, 'utf8')
    type = buf.readUInt8(0)

    if [0, 1, 2].indexOf(type) == -1
        return false

    return [type, buf.toString('utf8', 8, 8 + buf.readUInt32BE(4))]

parse_line = (line) ->
    if getopt.options['no-multiplex']
        return [null, line]
    else if getopt.options['multiplex']
        res = parse_with_multiplexing(line)

        if !res
            throw new Error('could not parse line')

        return res
    else
        res = parse_with_multiplexing(line)

        if res == false
            return [null, line]

        return res

attach = (container, channel) ->
    domain.create().on('error', (err) ->
        # most of the time it's dockerode replaying the callback when the connection is reset
        # see dockerode/lib/modem.js:87
        throw err unless err.code == 'ECONNRESET'
    ).run(->
        console.log('<- attaching container ' + container.id.substr(0, 12).yellow)
        container.attach({ logs: false, stream: true, stdout: true, stderr: true }, (err, stream) ->
            throw err if err

            # stream.on('end', -> console.log('   stream ended for container "' + container.id.yellow + '"'))

            stream.on('end', ->
                console.log('-> detaching container ' + container.id.substr(0, 12).yellow)
            )

            stream.on('data', (line) ->
                parsed = parse_line(line)

                if getopt.options.log
                    process.stdout.write(container.id.substr(0, 12).yellow + '> '+ parsed[1])

                # console.log('<- got ' + line.length + ' bytes from container "' + container.id.yellow + '"')
                message = { container: container.id, type: parsed[0], line: parsed[1] }
                buffer = new Buffer(JSON.stringify(message), 'utf8')

                channel.sendToQueue(opts.queue, buffer)
                # console.log('-> sent ' + buffer.length + ' bytes to "' + opts.queue + '"')
            )
        )
    )