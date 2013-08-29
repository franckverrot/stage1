(function($, window) {

    $(function() {
        var tpl_import = Mustache.compile($('#tpl-import').text());
        var tpl_nav_project = Mustache.compile($('#tpl-nav-projects').text());
        var tpl_nav_project_item = Mustache.compile($('#tpl-project-nav').text());
        var tpl_project_link = Mustache.compile($('#tpl-project-link').text());

        function on(event, callback) {
            primus.on('data', function(data) {
                console.log(data);
                if (data.event == event) {
                    callback(data.data);
                }
            });
        }

        on('project.import.start', function(data) {
            console.log(data);
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

            $('#organisations button')
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
                console.log(e);
                console.log(e.message);
                // throw e;
            }
        });
    })

    $('#organisations').on('click', '.candidate button', function() {
        $(this).html('<i class="icon-refresh icon-spin"></i>').attr('disabled', 'disabled');
        $('#organisations button').attr('disabled', 'disabled');

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

    window.github = function(url) {
        return $.ajax({
            url: url,
            type: 'GET',
            dataType: 'json',
            data: { 'access_token': github_access_token }
        });
    };

    window.fetch_composer_json = function(repo) {
        return $.ajax({
            url: repo.contents_url.replace('{+path}', 'composer.json'),
            context: repo,
            type: 'GET',
            dataType: 'json',
            data: { 'access_token': github_access_token },
            accepts: { json: 'application/vnd.github.VERSION.raw' }
        });
    };

    var candidates_count = 0;
    var organisations_count = 0;

    window.inspect_repositories = function(repos) {
        if (repos.length == 0) {
            return;
        }

        var status = function(message) {
            $('#projects_import_status').text(message);
        }

        var tpl_project = Mustache.compile($('#tpl-project').text());
        var tpl_project_existing = Mustache.compile($('#tpl-project-existing').text());
        var tpl_organisation = Mustache.compile($('#tpl-organisation').text());
        var projects_count = 0;

        var add_candidate = function(owner, html) {
            if ($('#org-' + owner.login).length == 0) {
                organisations_count++;
                $('#organisations').append(tpl_organisation({
                    'name': owner.login,
                    'avatar_url': owner.avatar_url
                }));
            }

            $('#org-' + owner.login + '-candidates').append(html);
        };


        projects_count = repos.length;
        for (i in repos) {
            if (!repos[i].permissions.admin) {
                continue;
            }

            fetch_composer_json(repos[i]).done(function(content) {
                for (pkg in content.require) {
                    if (pkg == 'symfony/symfony') {
                        candidates_count++;

                        if (undefined != existing_projects[this.full_name]) {
                            var project = tpl_project_existing({
                                name: this.full_name,
                                url: existing_projects[this.full_name]
                            });
                        } else {
                            var project = tpl_project({
                                name: this.full_name,
                                github_id: this.id,
                                data: [
                                    { name: 'name', value: this.name },
                                    { name: 'github_full_name', value: this.full_name },
                                    { name: 'github_owner_login', value: this.owner.login },
                                    { name: 'github_id', value: this.id },
                                    { name: 'clone_url', value: this.clone_url },
                                    { name: 'ssh_url', value: this.ssh_url },
                                    { name: 'hooks_url', value: this.hooks_url },
                                    { name: 'keys_url', value: this.keys_url }
                                ]
                            });
                        }

                        add_candidate(this.owner, project);
                    }
                }
            }).always(function() {
                if (--projects_count == 0) {
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
        }
    };

    window.find_repositories = function() {
        github(window.github_api_base_url + '/user/orgs').then(function(orgs) {
            orgs_count = orgs.length;

            for (i in orgs) {
                github(orgs[i].repos_url).then(inspect_repositories);
            }
        });

        github(window.github_api_base_url + '/user/repos').then(inspect_repositories);
    }
})(jQuery, window);