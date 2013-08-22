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
            url: $(this).parent().attr('action'),
            type: 'POST',
            dataType: 'json',
            data: data,
            context: this
        }).fail(function(jqXHR) {
            $(this)
                .html('<i class="icon-remove"></i> ' + jqXHR.responseJSON.message)
                .addClass('btn-' + jqXHR.responseJSON.class);
        }).done(function(response) {
            if (!$(this).hasClass('btn-success')) {
                $(this)
                    .html('<i class="' + $(this).data('success-class') + '"></i> ' + $(this).data('success-message'))
                    .removeClass()
                    .addClass('btn btn-small btn-success');                
            }
        });
    });

    window.detect_branches = function() {
        $.getJSON(detect_branches_url).then(function(data) {
            for (i in data) {
                var branch = data[i];
                branches.append(tpl_branch({
                    name: branch.ref,
                    normName: branch.ref.toLowerCase().replace(/[^a-z0-9\-]/, '-'),
                    hash: branch.hash,
                    abbr_hash: branch.hash.substr(0, 8),
                    data: [
                        { name: 'ref', value: branch.ref },
                        { name: 'hash', value: branch.hash }
                    ],
                    // @todo deprecated?
                    schedule_build_url: window.schedule_build_url
                }));

                do_update_ref(branch.last_build);
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