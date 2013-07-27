(function($, window) {

    $('#candidates').on('click', '.candidate button', function() {
        $(this).html('<i class="icon-refresh icon-spin"></i>').attr('disabled', 'disabled');

        var inputs = $(this).parent().find('input:hidden');
        var data = {};

        var tpl_project_nav  = Mustache.compile($('#tpl-project-nav').text());
        var tpl_project_link = Mustache.compile($('#tpl-project-link').text());

        inputs.each(function(index, item) {
            data[item.name] = item.value;
        });

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
                .html('<i class="icon-ok"></i> Success!')
                .addClass('btn-success');
            var project_link = tpl_project_link({ url: response.url, name: response.project.name });

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

    window.inspect_repositories = function() {
        var status = function(message) {
            $('#projects_import_status').text(message);
        }

        var tpl_project = Mustache.compile($('#tpl-project').text());
        var tpl_project_existing = Mustache.compile($('#tpl-project-existing').text());
        var candidates = $('#candidates');
        var projects_count = 0;
        var candidates_count = 0;

        github('https://api.github.com/user').then(function(data) {
            return github(data.repos_url);
        }).then(function(repos) {
            projects_count = repos.length;
            for (i in repos) {
                fetch_composer_json(repos[i]).done(function(content) {
                    for (pkg in content.require) {
                        if (pkg == 'symfony/symfony') {
                            candidates_count++;

                            if (undefined != existing_projects[this.name]) {
                                var project = tpl_project_existing({
                                    name: this.name,
                                    url: existing_projects[this.name]
                                });
                            } else {
                                var project = tpl_project({
                                    name: this.name,
                                    data: [
                                        { name: 'name', value: this.name },
                                        { name: 'github_id', value: this.id },
                                        { name: 'git_url', value: this.git_url },
                                        { name: 'hooks_url', value: this.hooks_url },
                                        { name: 'keys_url', value: this.keys_url }
                                    ]
                                });
                            }

                            candidates.append(project);
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
                            .text('Found ' + candidates_count + ' symfony project' + (candidates_count != 1 ? 's' : ''));
                    }
                });
            }
        });        
    }    
})(jQuery, window);