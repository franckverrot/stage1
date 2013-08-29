(function($, window) {
    var primus = Primus.connect('http://' + document.location.hostname + ':8090/', {
        privatePattern: /(project|user)\.\d+/,
        auth_url: websocket_auth_url,
    });

    var lastTimestamp = 0;

    window.primus = primus;

    primus.on('open', function() {
        primus.subscribe('user.' + current_user_id);
    });

    primus.on('data', function(data) {
        // console.log(data.event, '@', data.timestamp, 'vs', lastTimestamp);

        if (!data.data) {
            return;
        }

        if (data.timestamp <= lastTimestamp) {
            // outdated message, don't even bother
            // console.log('discarding outdated message');
            return
        }

        lastTimestamp = data.timestamp;

        if (data.data.build) {
            callbacks.build(event, data.data.build, data.data.project);
        }

        if (data.data.project) {
            callbacks.project(event, data.data.project);
        }

        if (typeof(callbacks[data.event]) == 'function') {
            callbacks[data.event](data.data);
        }
    });

    var prepare = [
        'nb-pending-builds',
        'build-kill-form'
    ];

    var tpl = {};

    for (i in prepare) {
        var tpl_id = prepare[i];
        if ($('#tpl-' + tpl_id).length > 0) {
            // console.log('preparing template', tpl_id);
            tpl[tpl_id] = Mustache.compile($('#tpl-' + tpl_id).text());
        }
    }

    var callbacks = {
        'project': function(event, project) {
            update_project_nb_pending_builds(project.id, parseInt(project.nb_pending_builds));
        },
        
        'build': function(event, build, project) {
            update_build(build.id, 'status', function(el) {
                el.data('status', build.status).removeClass().addClass('label label-' + build.status_label_class).html(build.status_label);
            });

            update_build(build.id, 'progress', function(el) {
                el.remove();
            });

            update_build(build.id, 'kill-form', function(el) {
                if ($('button i', el).hasClass('icon-refresh')) {
                    $('button', el).html('<i class="icon-ok"></i>');
                    setTimeout(function() { el.remove(); }, 1000);                    
                } else {
                    el.remove();
                }
            });

            update_build(build.id, 'cancel-form', function(el) {
                if ($('button i', el).hasClass('icon-refresh')) {
                    $('button', el).html('<i class="icon-ok"></i>');
                    setTimeout(function() { el.remove(); }, 1000);                    
                } else {
                    el.remove();
                }
            });

            // if (null != build.port && build.port.length > 0) {
            //     update_build(build.id, 'link', function(el) {
            //         var url = 'http://' + document.location.hostname + ':' + build.port + '/';
            //         el.html('<a href="' + url + '">' + url + '</a>');
            //     });
            // }

            if (null != build.url && build.url.length > 0) {
                update_build(build.id, 'link', function(el) {
                    if (el.data('template')) {
                        el.html(Mustache.render(el.data('template'), { build_url: build.url }));
                    } else {
                        el.html('<a href="' + build.url + '">' + build.url + '</a>');
                    }
                });
            }

            if (build.kill_url) {
                update_build(build.id, 'actions', function(el) {
                    if (undefined !== tpl['build-kill-form']) {
                        el.append(tpl['build-kill-form'](build));
                    }
                });
            }

            if (project.id && current_project_id == project.id) {
                do_update_ref(build);
            }
        }
    };

    function update_build(build_id, type, callback) {
        // console.log('update_build', build_id, type);
        callback($('#build-' + build_id + '-' + type));
    }

    function update_project_nb_pending_builds(project_id, nb_pending_builds) {
        var $nbPendingBuilds = $('#nav-project-' + project_id + ' #nb-pending-builds-' + project_id);

        if ($nbPendingBuilds.length == 0) {
            $('#nav-project-' + project_id).append(tpl['nb-pending-builds']({
                project_id: project_id,
                nb_pending_builds: nb_pending_builds
            }));
        } else {
            if (nb_pending_builds > 0) {
                $('span', $nbPendingBuilds).html(nb_pending_builds);                    
            } else {
                $nbPendingBuilds.remove();
            }
        }
    }

})(jQuery, window);