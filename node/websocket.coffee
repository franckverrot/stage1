#!/usr/bin/coffee

require 'coffee-script'

Primus            = require 'primus'
http              = require 'http'
{PrimusChannels}  = require './primus-channels.coffee'

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
    console.log 'spark connected', spark.id

port = 8090
Socket = primus.Socket
server.listen port, ->
    console.log 'listening on port ' + port