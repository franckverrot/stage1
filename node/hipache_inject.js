'use strict';

var harmon = require('harmon');
var through = require('through');

module.exports = function(hipache, req, stack, resolve) {

    var url_format = hipache.cache.config.server.config.url_format;
    var inject_script_url = hipache.cache.config.server.config.inject_script_url;
    var result = req.headers.host.match(new RegExp(url_format));

    if (null === result) {
        console.log('regular domain detected (' + req.headers.host + '), not injecting');
        return resolve();
    }

    var branch = result[1];
    var organization = result[2];
    var project = result[3];

    console.log('INFO: setting up inject middleware');

    stack.push(function(req, res, next) {
        var _write = res.write;
        var injected = false;
        res.write = function(chunk, encoding) {
            var string = chunk.toString();

            if (!injected && string.match(/<head>/)) {
                injected = true;
                var script =
                    '<head>\n' +
                    '<script type="text/javascript">var stage1 = { project: \'' + organization + '/' + project + '\', branch: \'' + branch + '\' };</script>\n' +
                    '<script type="text/javascript" src="' + inject_script_url + '"></script>\n';

                chunk = new Buffer(chunk.toString().replace('<head>', script));
            }

            _write.call(res, chunk, encoding);
        };
        next();
    });

    stack.push(function(req, res, next) {
        console.log('INFO: ==> ' + req.url);
        console.log('INFO: removing Accept-Encoding request header');
        delete(req.headers['accept-encoding']);
        next();
    });

    resolve();
}