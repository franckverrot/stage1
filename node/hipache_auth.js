
'use strict';

module.exports = function(hipache, req, stack, next) {

    if (req.headers.host.match(/^(?:[a-z0-9-]+\.)?stage1\.(?:io|dev)$/)) {
        console.log('regular domain detected (' + req.headers.host + '), not checking auth');
        return next();
    }

    var project = req.headers.host.split(/\./).splice(1, 2).join('-');
    var ip = req.connection.remoteAddress

    var cookies = {};

    if (req.headers.cookie) {
        req.headers.cookie.split(';').forEach(function(cookie) {
            var parts = cookie.split('=');
            cookies[parts[0].trim()] = (parts[1] || '').trim();
        });        
    }

    var token = cookies[hipache.cache.config.server.config.cookie_name];
    var auth_url = hipache.cache.config.server.config.auth_url;

    console.log('======================================')
    console.log('url: ' + req.url);
    console.log('project: ' + project);
    console.log('ip: ' + ip);
    console.log('token: ' + token);

    if (undefined === project) {
        console.log('no project detected, moving on');
        return next(); // next module
    }

    var auth_key = 'auth:' + project;
    var redis = hipache.cache.redisClient;

    console.log('checking auth for ' + project);

    redis.EXISTS(auth_key, function(err, exists) {
        if (err) {
            throw err;
        }

        if (exists) {
            console.log('project has auth activated, pushing middleware');
            stack.push(function(req, res, next) {
                redis.multi()
                    .sismember(auth_key, ip)
                    .sismember(auth_key, token)
                    .exec(function(err, rows) {
                        if (err) {
                            throw err;
                        }

                        console.log('[ip, token]');
                        console.log(rows);

                        if (-1 === rows.indexOf(1)) {
                            console.log('sorry bobby, not this time')
                            // @todo there HAS to be a better http status than that
                            res.writeHead(302, {
                                'Location': auth_url.replace(/{slug}/, project) + '?return=' + encodeURIComponent(req.headers.host)
                            });
                            res.end();
                        } else {
                            console.log('access granted!');
                            next();
                        }
                    });
            });
        } else {
            console.log('project does not have auth activated');
        }


        return next(); // next module
    });
}