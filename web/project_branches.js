(function($, window) {
    var tpl_branch = Mustache.compile($('#tpl-branch').text());
    var tpl_branch_pending = Mustache.compile($('#tpl-branch-pending').text());
    var tpl_nb_pending_builds = Mustache.compile($('#tpl-nb-pending-builds').text());
    var branches = $('#branches');

    $('#branches').on('click', '.branch button', function() {
        $(this).html('<i class="icon-refresh icon-spin"></i>').attr('disabled', 'disabled');

        var inputs = $(this).parent().find('input:hidden');
        var data = {};

        inputs.each(function(index, item) {
            data[item.name] = item.value;
        });

        $.ajax({
            url: schedule_build_url,
            type: 'POST',
            dataType: 'json',
            data: data,
            context: $(this).parent().parent()
        }).fail(function(jqXHR) {
            $('button', this)
                .html('<i class="icon-remove"></i> ' + jqXHR.responseJSON.message)
                .addClass('btn-' + jqXHR.responseJSON.class);
        }).done(function(response) {
            $('button', this)
                .html('<i class="icon-ok"></i> Success!')
                .addClass('btn-success');

            var $nbPendingBuilds = $('#nb-pending-builds-' + response.project_id);

            if ($nbPendingBuilds.length == 0) {
                $('#nav-project-' + response.project_id).append(tpl_nb_pending_builds(response));
            } else {
                $('span', $nbPendingBuilds).html(response.nb_pending_builds);
            }
        });
    });

    window.detect_branches = function() {
        $.getJSON(detect_branches_url).then(function(data) {
            for (i in data) {
                var branch = data[i];
                if (undefined != pending_builds[branch.ref]) {
                    branches.append(tpl_branch_pending({
                        name: branch.ref,
                        hash: branch.hash,
                        abbr_hash: branch.hash.substr(0, 8)                        
                    }));
                } else {
                    branches.append(tpl_branch({
                        name: branch.ref,
                        hash: branch.hash,
                        abbr_hash: branch.hash.substr(0, 8),
                        data: [
                            { name: 'ref', value: branch.ref },
                            { name: 'hash', value: branch.hash }
                        ]
                    }));                    
                }
            }

            $('#detect_branches_status')
                .removeClass('alert-info')
                .addClass('alert-success');

            $('#detect_branches_status i')
                .removeClass('icon-spin')
                .removeClass('icon-refresh')
                .addClass('icon-ok');

            $('#detect_branches_status span')
                .text('Found ' + data.length + ' branch' + (data.length != 1 ? 'es' : '') + '.');
        });
    };
})(jQuery, window);