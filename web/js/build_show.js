(function($, window) {
    window.stream_build_output = function(container) {
        var ws = new WebSocket('ws://' + document.location.hostname +':8888/');

        ws.onmessage = function(message) {
            container.append(ansi_up.ansi_to_html(message.data) + '\n');
            container[0].scrollTop = container[0].scrollHeight;
        };
    };
})(jQuery, window);