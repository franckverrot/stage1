require 'colors'

redis  = require 'redis'

@PrimusChannels =
    name: 'channels'
    client: (primus, options) ->
        primus.subscribe = (channel, token = null) ->
            subscribe = (token) -> primus.write action: 'subscribe', channel: channel, token: token

            if token? or not channel.match /^(private|presence)\-/
                subscribe token
            else
                $.post options.auth_url, { channel: channel }, (response) ->
                    response = JSON.parse(response)
                    if response.token
                        subscribe response.token

    server: (primus, options) ->
        primus.channels = {}
        primus.redis = redis.createClient()

        primus.Spark::subscribe = (channel, token) ->
            unless channel in primus.channels
                primus.channels[channel] = new Channel(channel, primus.redis, options)
            primus.channels[channel].subscribe this, token

        primus.Spark::unsubscribe = (channel) ->
            unless primus.channels[channel]?
                return
            primus.channels[channel].unsubscribe this

        primus.on 'connection', (spark) ->
            spark.write id: spark.id
            spark.on 'data', (data) ->
                return unless data.channel

                if data.action == 'subscribe'
                    spark.subscribe data.channel, data.token
                if data.action == 'unsubscribe'
                    spark.unsubscribe data.channel


class Channel
    constructor: (@name, @redis, @options) ->
        @sparks = []
        @isPrivate = @name.match /^(private|presence)\-/

    auth: (token, success, failure = ->) ->
        if @isPrivate
            @redis.sismember 'channel:' + @name, token, (err, result) ->
                throw err if err
                if result then success() else failure()
        else
            success()

    subscribe: (spark, token) ->
        @auth token,
            =>
                @sparks.push spark
                console.log ('subscribed spark#' + spark.id + ' to channel "' + @name + '"').green
            =>
                console.log ('spark#' + spark.id + ' failed authorization for channel "' + @name + '"').red

    unsubscribe: (spark) ->
        @sparks = (_ for _ in @sparks when _.id != spark.id)
        console.log ('unsubscribed spark#' + spark.id + ' from channel "' + @name + '"').green