(function($, window) {
    window.stream_build_output = function(container) {
        var ws = new WebSocket('ws://stage1:8888/websocket');

        ws.onmessage = function(message) {
            container.append(message.data + '\n');
            container[0].scrollTop = container[0].scrollHeight;
        };
    };
})(jQuery, window);