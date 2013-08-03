(function($, window) {
    $('.build-list').on('click', 'td.actions form button', function(event) {
        var $target = $(event.target);

        $target.html('<i class="icon-refresh icon-spin"></i>').attr('disabled', 'disabled');

        $.ajax({
            url: $target.parent().attr('action'),
            type: 'POST',
            dataType: 'json',
            context: event.target
        }).fail(function(jqXHR) {
            $(this)
                .html('<i class="icon-remove"></i> ' + jqXHR.responseJSON.message)
                .removeClass(function(index, classes) { return classes; })
                .addClass('btn btn-danger');
        }).done(function(response) {
            $(this)
                .html('<i class="' + $(this).data('success-class') + '"></i> ' + $(this).data('success-message'))
                .removeClass()
                .addClass('btn btn-small btn-success');

                var $nbPendingBuilds = $('#nb-pending-builds-' + response.project_id);

                if (response.nb_pending_builds == 0) {
                    $nbPendingBuilds.remove();
                } else {
                    $('span', $nbPendingBuilds).html(response.nb_pending_builds);
                }
        });
    });
})(jQuery, window);