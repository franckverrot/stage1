(function($, window) {

    var ws = new WebSocket('ws://' + document.location.hostname +':8889/');
    var lastTimestamp = 0;

    ws.onmessage = function(message) {
        var data = $.parseJSON(message.data);

        // console.log(data.event, '@', data.timestamp, 'vs', lastTimestamp);
        // console.log(data.data);

        if (data.timestamp <= lastTimestamp) {
            // outdated message, don't even bother
            // console.log('discarding outdated message');
            return
        }

        lastTimestamp = data.timestamp;

        if (data.data.build) {
            callbacks.build(event, data.data.build);
        }

        if (data.data.project) {
            callbacks.project(event, data.data.project);
        }

        if (typeof(callbacks[data.event]) == 'function') {
            callbacks[data.event](data.data);
        }
    };

    var prepare = [
        'nb-pending-builds',
        'build-kill-form',
        'ref-kill-form',
        'ref-schedule-form',
        'ref-status'
    ]

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

            if (null != build.port && build.port.length > 0) {
                update_build(build.id, 'link', function(el) {
                    var url = 'http://' + document.location.hostname + ':' + build.port + '/';
                    el.html('<a href="' + url + '">' + url + '</a>');
                });
            }

            if (null != build.url && build.url.length > 0) {
                update_build(build.id, 'link', function(el) {
                    el.html('<a href="' + build.url + '">' + build.url + '</a>');
                });
            }

            if (build.kill_url) {
                update_build(build.id, 'actions', function(el) {
                    if (undefined !== tpl['build-kill-form']) {
                        el.append(tpl['build-kill-form'](build));
                    }
                });
            }

            update_ref(build.normRef, 'status', function(el) {
                if (el.length == 0 && undefined != tpl['ref-status']) {
                    $('#ref-' + build.normRef + ' .ctn-status').html(tpl['ref-status']({
                        name: build.ref,
                        normName: build.normRef,
                        hash: build.hash,
                        status: build.status,
                        status_label: build.status_label,
                        status_label_class: build.status_label_class
                    }));
                } else {
                    el.data('status', build.status).removeClass().addClass('label label-' + build.status_label_class).html(build.status_label);
                }
            });


            update_ref(build.normRef, 'schedule-form', function(el) {
                if ($('button i', el).hasClass('icon-refresh')) {
                    $('button', el).removeClass().addClass('btn btn-small btn-success').html('<i class="icon-ok"></i>');
                    setTimeout(function() { el.remove(); }, 1000);                    
                } else {
                    el.remove();
                }
            });

            update_ref(build.normRef, 'kill-form', function(el) {
                if ($('button i', el).hasClass('icon-refresh')) {
                    $('button', el).html('<i class="icon-ok"></i>');
                    setTimeout(function() { el.remove(); }, 1000);                    
                } else {
                    el.remove();
                }
            });

            if (build.kill_url && tpl['ref-kill-form']) {
                update_ref(build.normRef, 'actions', function(el) {
                    el.append(tpl['ref-kill-form']({
                        name: build.ref,
                        normName: build.normRef,
                        kill_url: build.kill_url
                    }));
                });
            }

            if (build.schedule_url && tpl['ref-schedule-form']) {
                update_ref(build.normRef, 'actions', function(el) {
                    el.append(tpl['ref-schedule-form']({
                        name: build.ref,
                        normName: build.normRef,
                        schedule_build_url: build.schedule_url,
                        data: [
                            { name: 'ref', value: build.ref },
                        ]
                    }));
                });
            }
        }
    };

    function update_build(build_id, type, callback) {
        // console.log('update_build', build_id, type);
        callback($('#build-' + build_id + '-' + type));
    }

    function update_ref(norm_name, type, callback) {
        // console.log('update_ref', norm_name, type);
        callback($('#ref-' + norm_name + '-' + type));
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