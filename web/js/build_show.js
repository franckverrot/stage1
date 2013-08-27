(function($, window) {
    window.stream_build_output = function(container) {
        var latestPart = -1;
        var buffer = [];
        var autoScroll = true;

        $(container).scroll(function(event) {
            var t = event.target;
            if (t.clientHeight + t.scrollTop == t.scrollHeight) {
                autoScroll = true;
            } else {
                autoScroll = false;
            }
        });

        primus.on('data', function(data) {
            if (data.event == 'build.output.buffer') {
                for (i in data.data) {
                    buffer.push(data.data[i].data)
                }

                for (i in buffer) {
                    if (buffer[i].number == latestPart + 1) {
                        return processPart(buffer[i]);
                    }
                }
            }

            if (data.event == 'build.output') {
                return processPart(data.data);
            }
        });

        primus.write({ action: 'build.output.buffer' });

        function processPart(part) {
            if (part.number != latestPart + 1) {
                buffer.push(part)
                return false;
            }

            container.append(ansi_up.ansi_to_html(part.content));

            if (autoScroll) {
                container[0].scrollTop = container[0].scrollHeight;
            }

            latestPart = part.number;

            for (i in buffer) {
                if (buffer[i].number == part.number + 1) {
                    return processPart(buffer.splice(i, 1)[0]);
                }
            }

            return true;
        }
    };
})(jQuery, window);