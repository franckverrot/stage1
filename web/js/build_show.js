(function($, window) {
    // window.build_logs_load = function(container, build_id) {
    //     var url = build_logs_load_url.replace(/{id}/, build_id);
    // };

    window.stream_build_logs = function(container) {
        container[0].scrollTop = container[0].scrollHeight;

        var autoScroll = true;

        $(container).on('scroll', function(event) {
            var t = event.target;
            if (t.clientHeight + t.scrollTop == t.scrollHeight) {
                autoScroll = true;
            } else {
                autoScroll = false;
            }
        });

        primus.on('data', function(data) {
            if (data.event == 'build.log' && data.build_id == current_build_id) {
                container.append(data.content);
                if (autoScroll) {
                    container[0].scrollTop = container[0].scrollHeight;
                }
            }
        });
    };

    window.stream_build_output = function(container) {
        var latestPart = -1;
        var buffer = [];
        var autoScroll = true;

        $(container).on('scroll', function(event) {
            var t = event.target;
            if (t.clientHeight + t.scrollTop == t.scrollHeight) {
                autoScroll = true;
            } else {
                autoScroll = false;
            }
        });

        primus.on('data', function(data) {

            if (data.event == 'build.output.buffer') {
                console.log('processing buffered data');
                for (i in data.data) {
                    if (data.data[i].event && data.data[i].event == 'build.log') {
                        processPart(data.data[i].data);
                    } else {
                        console.log('skipping buffered event "' + data.data[i].event + '"');
                    }
                }
            }

            if (data.event == 'build.log') {
                processPart(data.data);
            }
        });

        function processPart(part) {
            console.log(part);

            if (!part.build) {
                console.log('no build information');
                return;
            }

            var build = part.build;

            if (build.id != current_build_id) {
                console.log('expected build.id #' + current_build_id + ', got ' + part.build.id);
                return;
            }

            // console.log('processing part #' + part.number + ' for build #' + part.build.id);
            console.log('message: ', part.message);

            // if (part.number != latestPart + 1) {
            //     buffer.push(part)
            //     return false;
            // }

            container.append(ansi_up.ansi_to_html(part.message));

            if (autoScroll) {
                container[0].scrollTop = container[0].scrollHeight;
            }

            // latestPart = part.number;

            // for (i in buffer) {
            //     if (buffer[i].number == part.number + 1) {
            //         return processPart(buffer.splice(i, 1)[0]);
            //     }
            // }

            return true;
        }
    };
})(jQuery, window);