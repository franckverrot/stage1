(function($, window) {
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

        var tpl = Mustache.compile($('#tpl-project').text());
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
                            candidates.append(tpl({
                                name: this.name,
                                data: [
                                    { name: 'name', value: this.name },
                                    { name: 'github_id', value: this.id },
                                    { name: 'git_url', value: this.git_url },
                                    { name: 'hooks_url', value: this.hooks_url },
                                    { name: 'keys_url', value: this.keys_url }
                                ]
                            }));
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