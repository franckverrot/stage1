#!/usr/bin/coffee

#
# Format of piped messages:
#
# { container:  (string)    container id,
#   type:       (int)       stream type (0 = stdin, 1 = stdout, 2 = stderr),
#   line:       (string)    actual log line }
#

VERSION = '0.0.1'

getopt = require('node-getopt').create([
    ['d', 'docker=ARG', 'Docker DSN (eg: /var/run/docker.sock or 127.0.0.1:4243)'],
    ['a', 'amqp=ARG', 'AMQP DSN (eg: localhost)'],
    ['q', 'queue=ARG', 'queue to pipe messages to'],
    ['A', 'attach', 'attach already running containers'],
    ['l', 'log', 'output logs as they arrive'],
    ['h', 'help', 'show this help'],
    ['v', 'version', 'show program version']
])
.bindHelp()
.parseSystem()

if getopt.options.version
    return console.log('Aldis ' + VERSION)

opts =
    docker: getopt.options.docker || '/var/run/docker.sock'
    amqp:   getopt.options.amqp   || 'localhost'
    queue:  getopt.options.queue  || 'docker_output'

Docker = require('dockerode')
amqp   = require('amqplib')
colors = require('colors')
domain = require('domain')

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
            return unless containers
            containers.forEach (data) ->
                attach(docker.getContainer(data.Id), channel)

    docker.getEvents(null, (err, stream) ->
        throw err if err
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

attach = (container, channel) ->
    domain.create().on('error', (err) ->
        # most of the time it's dockerode replaying the callback when the connection is reset
        # see dockerode/lib/modem.js:87
        throw err unless err.code == 'ECONNRESET'
    ).run(->
        console.log('<- attaching container ' + container.id.substr(0, 12).yellow)
        use_multiplexing = true

        container.inspect((err, info) ->
            throw err if err

            if info.Config.tty
                use_multiplexing = false
        )

        container.attach({ logs: false, stream: true, stdout: true, stderr: true }, (err, stream) ->
            throw err if err

            # stream.on('end', -> console.log('   stream ended for container "' + container.id.yellow + '"'))

            stream.on('end', ->
                console.log('-> detaching container ' + container.id.substr(0, 12).yellow)
            )

            stream.on('data', (line) ->
                parsed = parse_line(line, use_multiplexing)

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

parse_line = (line, use_multiplexing) ->
    if !use_multiplexing
        return line

    # @see http://docs.docker.io/en/master/api/docker_remote_api_v1.7/#attach-to-a-container
    buf = new Buffer(line, 'utf8')
    type = buf.readUInt8(0)

    if [0, 1, 2].indexOf(type) == -1
        throw new Error('Unknown stream type ' + type)

    return [type, buf.toString('utf8', 8, 8 + buf.readUInt32BE(4))]
