(function($, window) {

    var ws = new WebSocket('ws://stage1:8889');

    ws.onmessage = function(message) {
        var data = $.parseJSON(message.data);

        console.log(data.event);

        if (data.data.build) {
            callbacks.build(data.data.build);
        }

        if (data.data.project) {
            callbacks.project(data.data.project);
        }

        if (typeof(callbacks[data.event]) == 'function') {
            callbacks[data.event](data.data);
        }
    };

    var prepare = [
        'nb-pending-builds',
        'build-kill-form',
        'ref-status'
    ]

    var tpl = {};

    for (i in prepare) {
        var tpl_id = prepare[i];
        if ($('#tpl-' + tpl_id).length > 0) {
            console.log('preparing template', tpl_id);
            tpl[tpl_id] = Mustache.compile($('#tpl-' + tpl_id).text());
        }
    }

    var callbacks = {
        'project': function(project) {
            update_project_nb_pending_builds(project.id, parseInt(project.nb_pending_builds));
        },
        'build': function(build) {
            update_build(build.id, 'status', function(el) {
                var previousStatus = parseInt(el.data('status'));
                if (previousStatus < build.status) {
                    el.data('status', build.status).removeClass().addClass('label label-' + build.status_label_class).html(build.status_label);
                }
            });

            update_ref(build.ref, 'status', function(el) {
                if (el.length == 0 && undefined != tpl['ref-status']) {
                    $('#ref-' + build.ref + ' .ctn-status').html(tpl['ref-status']({
                        name: build.ref,
                        status: build.status,
                        status_label: build.status_label,
                        status_label_class: build.status_label_class
                    }));
                } else {
                    var previousStatus = parseInt(el.data('status'));
                    if (previousStatus < build.status) {
                        el.data('status', build.status).removeClass().addClass('label label-' + build.status_label_class).html(build.status_label);
                    }
                }
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

            update_ref(build.ref, 'schedule-form', function(el) {
                if ($('button i', el).hasClass('icon-refresh')) {
                    $('button', el).removeClass().addClass('btn btn-small btn-success').html('<i class="icon-ok"></i>');
                    setTimeout(function() { el.remove(); }, 1000);                    
                } else {
                    el.remove();
                }
            });

                console.log(build.url, null != build.url);
            if (null != build.url && build.url.length > 0) {
                console.log('updating url', build.id, build.url);
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
        }
    };

    function update_build(build_id, type, callback) {
        console.log('update_build', build_id, type);
        callback($('#build-' + build_id + '-' + type));
    }

    function update_ref(ref_name, type, callback) {
        console.log('update_ref', ref_name, type);
        callback($('#ref-' + ref_name + '-' + type));
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