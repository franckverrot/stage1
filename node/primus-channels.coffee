redis  = require 'redis'
crypto = require 'crypto'

@PrimusChannels =
    name: 'channels'
    client: (primus, options) ->
        primus.on 'data', (data) ->
            primus.id = data.id if data.id

        primus.subscribe = (channel) ->
            $.post options.auth_url, { spark_id: primus.id, channel: channel }, (response) ->
                if response
                    primus.write action: 'subscribe', channel: channel
            , 'json'

            

    server: (primus, options) ->
        primus.channels = {}
        primus.redis = redis.createClient()

        primus.Spark::subscribe = (channel) ->
            unless channel in primus.channels
                primus.channels[channel] = new Channel(channel, primus.redis, options)
            primus.channels[channel].subscribe this

        primus.Spark::unsubscribe = (channel) ->
            unless primus.channels[channel]?
                return
            primus.channels[channel].unsubscribe this

        primus.on 'connection', (spark) ->
            spark.write id: spark.id
            spark.on 'data', (data) ->
                if data.channel and (data.action in ['subscribe', 'unsubscribe'])
                    spark[data.action] data.channel


class Channel
    constructor: (@name, @redis, @options) ->
        @sparks = []

    sign: (spark) ->
        crypto
            .createHmac('sha256', @options.secret)
            .update(spark.id + ':' + @name)
            .digest('hex')

    auth: (spark, success, failure = ->) ->
        console.log @sign(spark)
        @redis.sismember 'channel:' + @name, @sign(spark), (err, result) ->
            throw err if err
            if result then success() else failure()

    subscribe: (spark) ->
        @auth spark,
            =>
                @sparks.push spark
                console.log 'subscribed spark#' + spark.id + ' to channel "' + @name + '"'
            =>
                console.log('spark#' + spark.id + ' failed authorization for channel "' + @name + '"')

    unsubscribe: (spark) ->
        @sparks = (_ for _ in @sparks when _.id != spark.id)
        console.log 'unsubscribed spark#' + spark.id + ' from channel "' + @name + '"'