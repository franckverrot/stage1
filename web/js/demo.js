function demo_websocket_listen(websocket_channel) {

    console.log('listening for demo build on channel "' + websocket_channel + '"');

    var primus = Primus.connect('http://' + document.location.hostname + ':8090/', {
        privatePattern: /(project|user)\.\d+/,
        auth_url: websocket_auth_url,
    });

    var lastTimestamp = 0;

    primus.on('open', function() {
        primus.subscribe(websocket_channel);
    });

    primus.on('data', function(message) {
        console.log(message);

        if (!message.data.build && message.event !== 'build.output.buffer') { //} || message.data.build.id !== current_build_id) {
            console.log('invalid message', message);
            return;
        }

        console.log('received event "' + message.event + '"');

        processMessage(message);
    });


    function processMessage(message) {
        if (message.event === 'build.output.buffer' && is_building) {
            for (i in message.data) {
                processMessage(message.data[i]);
            }
        }

        if (null === message.data.progress) {
            progress = 100;
        } else {
            progress = message.data.progress;
        }

        $('#build-progress .bar').css('width', progress + '%');

        if (message.event === 'build.scheduled') {
            var content = Mustache.render($('#tpl-steps').text(), { 'steps': message.data.steps });
            $('#build-steps').html(content);

            var content = Mustache.render($('#tpl-meta').text(), { project: message.data.project.name });
            $('#build-meta').html(content);

            if (message.data.previousBuild && message.data.previousBuild.duration) {
                console.log('rendering duration', message.data.previousBuild.duration);
                var duration = Math.ceil(message.data.previousBuild.duration / 60);
                var content = Mustache.render($('#tpl-duration').text(), {
                    duration: duration,
                    unit: (duration == 1 ? 'minute' : 'minutes')
                });
                $('#build-meta').append(content);
                console.log('rendered duration');
            }

            console.log('hiding form');
            $('#form-build').hide();

            $('#steps li').tooltip();

            return;
        }

        if (message.event === 'build.started') {
            $('#steps li.pending:first')
                .removeClass('pending')
                .addClass('running')
                .find('i')
                    .removeClass()
                    .addClass('icon-refresh icon-spin');
            return;
        }

        if (message.event === 'build.finished') {
            if (['failed', 'killed'].indexOf(message.data.build.status_label) != -1) {
                $('#build-meta').html(Mustache.render($('#tpl-failed').text()));
                $('#build-steps').remove();
            } else {
                $('#build-progress').removeClass('active');
                $('#build-progress .bar').css('width', '100%');
            
                $('#steps li')
                    .removeClass()
                    .addClass('done')
                    .find('i')
                        .removeClass()
                        .addClass('icon-ok');

                var url = Mustache.render($('#tpl-url').text(), { 'url': message.data.build.url });
                $('#build-url').html(url);                
            }
            
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