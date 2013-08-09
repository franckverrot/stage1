(function($, window) {

    $('#organisations').on('click', '.candidate button', function() {
        $(this).html('<i class="icon-refresh icon-spin"></i>').attr('disabled', 'disabled');

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
            $('button', this)
                .html('<i class="icon-remove"></i> ' + jqXHR.responseJSON.message)
                .addClass('btn-danger');
        }).done(function(response) {
            $('button', this)
                .html('<i class="icon-ok"></i> Success')
                .addClass('btn-success');
            var project_link = tpl_project_link({ url: response.url, name: response.project.full_name });

            $('.ctn-name', this).html(project_link);
            $('#nav-projects').append(tpl_project_nav({ link: project_link }));
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
                console.log(owner);
                $('#organisations').append(tpl_organisation({
                    'name': owner.login,
                    'avatar_url': owner.avatar_url
                }));
            }

            $('#org-' + owner.login + '-candidates').append(html);
        };


        projects_count = repos.length;
        for (i in repos) {
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