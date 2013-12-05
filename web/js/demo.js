function demo_websocket_listen(websocketChannel) {

    console.log('listening for demo build on channel "' + websocketChannel + '"');

    var primus = Primus.connect('http://' + document.location.hostname + ':8090/', {
        privatePattern: /(project|user)\.\d+/,
        auth_url: websocket_auth_url,
    });

    var lastTimestamp = 0;
    var receivedMessages = 0;
    var expectedMessages = 0;

    primus.on('open', function() {
        primus.subscribe(websocketChannel);
    });

    primus.on('data', function(message) {
        console.log(message);

        if (!message.data.build && message.event !== 'build.output.buffer') { //} || message.data.build.id !== current_build_id) {
            console.log('invalid message', message);
            return;
        }

        console.log('received event "' + message.event + '"');

        try {
            processMessage(message);
        } catch (e) {
            console.log('EXCEPTION');
            console.log(e);
        }
    });


    function processMessage(message) {
        if (message.event == 'build.output.buffer' && is_building) {
            console.log('processing "build.output.buffer" event');
            for (i in message.data) {
                console.log(message.data[i]);
                processMessage(message.data[i]);
            }

            return;
        }

        if (message.event == 'build.log' && expectedMessages > 0) {
            console.log('processing "build.log" event');
            receivedMessages++;

            progress = (receivedMessages / expectedMessages) * 100;
            $('#build-progress .bar').css('width', progress + '%');
        }

        if (message.event === 'build.scheduled') {
            console.log('processing "build.scheduled" event');

            var content = Mustache.render($('#tpl-steps').text(), { 'steps': message.data.steps });
            $('#build-steps').html(content);

            console.log('rendered steps');

            var content = Mustache.render($('#tpl-meta').text(), { project: message.data.build.project.name });
            $('#build-meta').html(content);

            console.log('rendered meta');

            console.log('data:', message.data);

            if (message.data.previousBuild) {
                previousBuild = message.data.previousBuild;
                console.log('got previous Build');
                console.log(previousBuild);

                if (previousBuild.duration) {
                    console.log('rendering duration', previousBuild.duration);
                    var duration = Math.ceil(previousBuild.duration / (60 * 1000));
                    var content = Mustache.render($('#tpl-duration').text(), {
                        duration: duration,
                        unit: (duration == 1 ? 'minute' : 'minutes')
                    });
                    $('#build-meta').append(content);
                    console.log('rendered duration');
                }

                if (previousBuild.output_logs_count) {
                    console.log('expecting ' + previousBuild.output_logs_count);
                    expectedMessages = previousBuild.output_logs_count;
                }
            }

            console.log('hiding form');
            $('#form-build').hide();

            $('#steps li').tooltip();

            return;
        }

        if (message.event === 'build.started') {
            console.log('processing "build.started" event');
            $('#steps li.pending:first')
                .removeClass('pending')
                .addClass('running')
                .find('i')
                    .removeClass()
                    .addClass('icon-refresh icon-spin');
            return;
        }

        if (message.event === 'build.finished') {
            console.log('processing "build.finished" event');

            if (['failed', 'killed'].indexOf(message.data.build.status_label) != -1) {
                $('#build-meta').html(Mustache.render($('#tpl-failed').text()));
            } else {
                $('#build-progress').removeClass('active');
                $('#build-progress .bar').css('width', '100%');
            
                $('#steps li')
                    .removeClass()
                    .addClass('done')
                    .find('i')
                        .removeClass()
                        .addClass('icon-ok');

                var url = Mustache.render($('#tpl-url').text(), {
                    url: message.data.build.url,
                    project: message.data.project.name
                });

                $('#build-url').html(url);
                $('#build-meta').hide();
            }
            
            $('#build-steps').hide();
            return;
        }

        if (message.event !== 'build.step') {
            return;
        }

        console.log('received step "' + message.data.announce.step + '"');

        if ($('#' + message.data.announce.step).length == 0) {
            console.log('unrecognized step, skipping');
            return;
        }


        $('#steps li.running')
            .not('#' + message.data.announce.step)
            .removeClass('running')
            .prevAll('li')
                .removeClass()
            .addBack()
                .addClass('done')
                .find('i')
                    .removeClass()
                    .addClass('icon-ok');

        $('#steps li#' + message.data.announce.step)
            .removeClass('pending')
            .addClass('running')
                .find('i')
                    .removeClass()
                    .addClass('icon-refresh icon-spin');
    }
}