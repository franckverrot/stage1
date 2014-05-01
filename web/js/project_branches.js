(function($, window) {
    var branches = $('#branches');

    $('#branches, #pull-requests').on('click', '.branch button, .pull-request button', function(event) {
        $(this).html('<i class="fa fa-refresh fa-spin"></i>').attr('disabled', 'disabled');

        var inputs = $(this).parent().find('input:hidden');
        var data = {};

        inputs.each(function(index, item) {
            data[item.name] = item.value;
        });

        event.preventDefault();

        $.ajax({
            url: $(this).parent().attr('action'),
            type: 'POST',
            dataType: 'json',
            data: data,
            context: this
        }).fail(function(jqXHR) {
            $(this)
                .html('<i class="fa fa-times"></i> ' + jqXHR.responseJSON.message)
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