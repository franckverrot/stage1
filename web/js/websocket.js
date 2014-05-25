(function($, window) {
    if (typeof(current_user_id) === 'undefined') { return; }

    var primus = Primus.connect('ws://' + document.location.hostname + ':' + websocket_port, {
        // privatePattern: /(project|user)\.\d+/,
        privatePattern: /.*/,
        auth_url: websocket_auth_url
    });

    var lastTimestamp = 0;

    window.primus = primus;

    primus.on('open', function() {
        primus.subscribe();
    });

    primus.on('data', function(data) {

        if (data.event) {
            // console.log(data.event, data.channel, '@', data.timestamp, 'vs', lastTimestamp);
        } else {
            // console.log(data);
        }

        if (!data.data) {
            return;
        }

        // console.log(data.data);

        if (data.timestamp && data.timestamp <= lastTimestamp) {
            // outdated message, don't even bother
            // console.log('discarding outdated message');
            return
        } else {
            lastTimestamp = data.timestamp;
        }


        if (data.event == 'build.log' || data.event == 'data.output.buffer') {
            return;
        }

        if (data.data.build) {
            callbacks.build(data.event, data.data.build);
        }

        if (data.data.build && data.data.build.project) {
            callbacks.project(data.event, data.data.build.project);
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
        
        'build': function(event, build) {
            update_build(build.id, 'status', function(el) {
                el.data('status', build.status).removeClass().addClass('label label-' + build.status_label_class).html(build.status_label);
            });

            if (event === 'build.finished') {
                update_build(build.id, 'progress', function(el) {
                    el.remove();
                });                
            }

            update_build(build.id, 'kill-form', function(el) {
                if ($('button i', el).hasClass('fa-refresh')) {
                    $('button', el).html('<i class="fa fa-check"></i>');
                    setTimeout(function() { el.remove(); }, 1000);                    
                } else {
                    el.remove();
                }
            });

            update_build(build.id, 'cancel-form', function(el) {
                if ($('button i', el).hasClass('fa-refresh')) {
                    $('button', el).html('<i class="fa fa-check"></i>');
                    setTimeout(function() { el.remove(); }, 1000);                    
                } else {
                    el.remove();
                }
            });

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

            if (build.project && build.project.id && current_project_id == build.project.id) {
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