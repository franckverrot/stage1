(function($, window) {

    var ws = new WebSocket('ws://stage1:8889');

    ws.onmessage = function(message) {
        var data = $.parseJSON(message.data);
        console.log(data);
        callbacks[data.event](data.data);
    };

    var tpl_nb_pending_builds = Mustache.compile($('#tpl-nb-pending-builds').text());

    var callbacks = {
        'build.started': function(data) {
            update_project_nb_pending_builds(data.project.id, parseInt(data.project.nb_pending_builds));
        },

        'build.finished': function(data) {
            console.log('build.finished');
            update_project_nb_pending_builds(data.project.id, parseInt(data.project.nb_pending_builds));

            update(data.build.id, 'status', function(el) {
                console.log('updating status');
                console.log(el);
                el.removeClass().addClass('label label-' + data.build.status_label_class).html(data.build.status_label);
            });

            update(data.build.id, 'progress', function(el) {
                console.log('updating progress');
                console.log(el);
                el.remove();
            });

            update(data.build.id, 'link', function(el) {
                el.html('<a href="' + data.build.url + '">' + data.build.url + '</a>');
            });

            update(data.build.id, 'kill-form', function(el) {
                $('button', el).html('<i class="icon-ok"></i>');
                setTimeout(function() { el.remove(); }, 1000);
            });

            update(data.build.id, 'cancel-form', function(el) { el.remove(); });
        }
    };

    function update(build_id, type, callback) {
        callback($('#build-' + build_id + '-' + type));
    }

    function update_project_nb_pending_builds(project_id, nb_pending_builds) {
        console.log('updating pending builds count');
        console.log(project_id, nb_pending_builds);
        var $nbPendingBuilds = $('#nav-project-' + project_id + ' #nb-pending-builds-' + project_id);

        if ($nbPendingBuilds.length == 0) {
            console.log('adding');
            $('#nav-project-' + project_id).append(tpl_nb_pending_builds({
                project_id: project_id,
                nb_pending_builds: nb_pending_builds
            }));
        } else {
            if (nb_pending_builds > 0) {
                console.log('updating');
                $('span', $nbPendingBuilds).html(nb_pending_builds);                    
            } else {
                console.log('removing');
                $nbPendingBuilds.remove();
            }
        }
    }

})(jQuery, window);