(function($, window) {

    var tpl_nav_project = Mustache.compile($('#tpl-nav-projects').text());
    var tpl_nav_project_item = Mustache.compile($('#tpl-project-nav').text());
    var tpl_project_link = Mustache.compile($('#tpl-project-link').text());

    $(function() {
        if (typeof(primus) === 'undefined') { return; }

        var tpl_import = Mustache.compile($('#tpl-import').text());

        function on(event, callback) {
            primus.on('data', function(data) {
                // console.log(data);
                if (data.event == event) {
                    callback(data.data);
                }
            });
        }

        on('project.import.start', function(data) {
            // console.log(data);
            $('#candidate-' + data.project_github_id + ' button').addClass('btn-success');

            $('#progress').html(tpl_import(data));
        });

        on('project.import.step', function(data) {
            $('#steps li.running')
                .removeClass('running')
                .addClass('done')
                .find('i')
                    .removeClass()
                    .addClass('icon-ok');

            $('#steps li#' + data.step)
                .removeClass('pending')
                .addClass('running')
                    .find('i')
                        .removeClass()
                        .addClass('icon-refresh icon-spin');
        });

        on('project.import.finished', function(data) {
            $('#steps li.running')
                .removeClass('running')
                .addClass('done')
                .find('i')
                    .removeClass()
                    .addClass('icon-ok');

            $('#organisations button.btn-import')
                .not('#candidate-' + data.project_github_id + ' button')
                .not('.btn-success')
                .not('.btn-info')
                .attr('disabled', false);

            $('#candidate-' + data.project_github_id + ' button i').removeClass().addClass('icon-ok');

            try {

                primus.subscribe(data.websocket_channel, data.websocket_token);

                var project_link = tpl_project_link({ url: data.project_url, name: data.project_full_name });

                if ($('#nav-projects').length == 0) {
                    $('#sidebar').prepend(tpl_nav_project());
                }

                $('#nav-projects').append(tpl_nav_project_item({ link: project_link }));
            } catch (e) {
                // console.log(e);
                // console.log(e.message);
                // throw e;
            }
        });
    });

    $('#organisations').on('click', '.candidate button.btn-join', function() {
        $(this).html('<i class="icon-refresh icon-spin"></i>').attr('disabled', 'disabled');

        $.ajax({
            url: $(this).data('join-url'),
            type: 'POST',
            dataType: 'json',
            context: $(this).parent().parent()
        }).fail(function(jqXHR, textStatus, errorThrown) {
            try {
                var message = JSON.parse(jqXHR.responseJSON).message;
            } catch (e) {
                var message = 'An unexpected error has occured (' + e.message + ')';
            }

            $('button', this).html('<i class="icon-remove"></i> ' + message).addClass('btn-danger');
        }).then(function(data) {
            data = JSON.parse(data);

            if (data.status == 'ok') {
                $('button', this).addClass('btn-success').html('<i class="icon-ok"></i>');

                var project_link = tpl_project_link({ url: data.project_url, name: data.project_full_name });

                if ($('#nav-projects').length == 0) {
                    $('#sidebar').prepend(tpl_nav_project());
                }

                $('#nav-projects').append(tpl_nav_project_item({ link: project_link }));
            }
        });
    });

    $('#organisations').on('click', '.candidate button.btn-import', function() {
        $(this).html('<i class="icon-refresh icon-spin"></i>').attr('disabled', 'disabled');
        $('#organisations button.btn-import').attr('disabled', 'disabled');

        var inputs = $(this).parent().find('input:hidden');
        var data = {};

        inputs.each(function(index, item) {
            data[item.name] = item.value;
        });

        var tpl_project_nav  = Mustache.compile($('#tpl-project-nav').text());
        var tpl_project_link = Mustache.compile($('#tpl-project-link').text());

        $.ajax({
            url: import_url,
            type: 'POST',
            dataType: 'json',
            data: data,
            context: $(this).parent().parent()
        }).fail(function(jqXHR, textStatus, errorThrown) {
            // @todo restore disabled state on buttons
            try {
                var message = jqXHR.responseJSON.message;
            } catch (e) {
                var message = 'An unexpected error has occured (' + e.message + ')';
            }

            $('button', this)
                .html('<i class="icon-remove"></i> ' + message)
                .addClass('btn-danger');                
        });
    });

    window.find_repositories = function() {
        var tpl_project = Mustache.compile($('#tpl-project').text());
        var tpl_project_existing = Mustache.compile($('#tpl-project-existing').text());
        var tpl_project_joinable = Mustache.compile($('#tpl-project-joinable').text());
        var tpl_organisation = Mustache.compile($('#tpl-organisation').text());

        var candidates_count = 0;
        var organisations_count = 0;


        $.get('/discover').then(function(data) {
            data = JSON.parse(data);

            // console.log(data);

            for (fullName in data) {
                candidates_count++;

                var project = data[fullName];

                if ($('#org-' + project.github_owner_login).length == 0) {
                    organisations_count++;

                    $('#organisations').append(tpl_organisation({
                        'name': project.github_owner_login,
                        'avatar_url': project.github_owner_avatar_url
                    }));
                }

                if (project.exists) {
                    if (project.is_in) {
                        var html = tpl_project_existing({
                            name: project.github_full_name,
                            url: project.url,
                            users: function users() { return project.users.join(', '); }
                        });                        
                    } else {
                        var html = tpl_project_joinable({
                            name: project.github_full_name,
                            url: project.url,
                            join_url: project.join_url,
                            users: function users() { return project.users.join(', '); }
                        });
                    }
                } else {
                    var html = tpl_project({
                        name: project.github_full_name,
                        github_id: project.github_id,
                        data: [
                            { name: 'name', value: project.name },
                            { name: 'github_full_name', value: project.github_full_name },
                            { name: 'github_owner_login', value: project.github_owner_login },
                            { name: 'github_id', value: project.github_id },
                            { name: 'clone_url', value: project.clone_url },
                            { name: 'ssh_url', value: project.ssh_url },
                            { name: 'hooks_url', value: project.hooks_url },
                            { name: 'keys_url', value: project.keys_url }
                        ]
                    });
                }

                $('#org-' + project.github_owner_login + '-candidates').append(html);

                $('#projects_import_status')
                    .removeClass('alert-info')
                    .addClass('alert-success');

                $('#projects_import_status i')
                    .removeClass('icon-refresh')
                    .removeClass('icon-spin')
                    .addClass('icon-ok');

                $('#projects_import_status span')
                    .text('Found ' + candidates_count + ' Symfony project' + (candidates_count != 1 ? 's' : '') + ' in ' + organisations_count + ' organisation' + (organisations_count != 1 ? 's' : '') + '.');
            }
        });
    };
})(jQuery, window);