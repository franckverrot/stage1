(function($, window) {
    $('#builds').on('click', 'td.actions form button', function(event) {
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
                .addClass('btn btn-danger')
        }).done(function(response) {
            $(this)
                .html('<i class="icon-ok"></i> ' + $(this).data('success-message'))
                .removeClass(function(index, classes) { return classes; })
                .addClass('btn btn-success')
        });
    });
})(jQuery, window);