$('#feedback').on('shown', function(event) {
    $(event.target).find('textarea').focus();
});

$('#feedback').on('hidden', function(event) {
    $('#feedback-compose-button a')[0].blur();
})