(function($, window) {
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
})(jQuery, window);