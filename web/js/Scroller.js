var Scroller = function(container, url, options) {
    this.options = $.extend({
        loadingIndicator: $('#logs-loading-indicator')[0],
        threshold: 100,
        firstPage: -1
    }, options);

    this.container = container;
    this.url = url;

    this.initialScrollHeight = container.scrollHeight;
    this.nextPage = null;
    this.loading = false;
    this.preLoading = true;

    this.init = function(page) {
        this.loadPage(page || this.options.firstPage, function(data, scroller) {
            $(scroller.container).on('scroll', function(event) {
                if (scroller.nextPage === null || scroller.loading) {
                    return;
                }

                var t = event.target;

                if (t.scrollTop <= scroller.options.threshold) {
                    scroller.loadPage(scroller.nextPage);
                } else {
                    loading = false;
                }
            });
        });
    }

    this.loadPage = function(page, callback) {
        $(this.options.loadingIndicator).show();
        this.loading = true;

        var scroller = this;

        $.ajax({
            url: url,
            data: { page: page },
            success: function(data) {
                var currentTopElement = $(scroller.container).children().first();

                for (i = data.items.length; i > 0; i--) {
                    var fragment = data.items[i - 1];
                    $(scroller.container).prepend('<span id="fragment-' + fragment.fragment_id + '">' + ansi_up.ansi_to_html(fragment.message) + '</span>');

                    if (currentTopElement.length > 0 && !scroller.preLoading) {
                        scroller.container.scrollTop = currentTopElement.position().top;
                    } else {
                        scroller.container.scrollTop = scroller.container.scrollHeight;
                    }
                }

                var delta = scroller.container.scrollHeight - scroller.initialScrollHeight;

                if (delta <= scroller.options.threshold && data.previous_page !== null) {
                    scroller.preLoading = true;
                    scroller.loadPage(data.previous_page, callback);
                } else {
                    scroller.preLoading = false;
                    scroller.nextPage = data.previous_page;
                    scroller.loading = false;

                    if (typeof(callback) === 'function') {
                        callback(data, scroller);
                    }

                    $(scroller.options.loadingIndicator).hide();                
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                var container = $(scroller.container).parent();
                $(scroller.container).remove();

                if (jqXHR.responseJSON) {
                    if (false && jqXHR.responseJSON.total === 0) {
                        $(container).prepend('<p class="lead">There are no output logs for this build.</p>');
                    } else {
                        $(container).prepend('<p class="lead">Loading output logs failed with the following message: <strong>' + jqXHR.responseJSON.error + '</strong>.</p>');
                    }
                } else {
                    $(container).prepend('<p class="lead">Loading output logs failed with the following message: <strong>' + error + '</strong>.</p>');
                }

                $(scroller.options.loadingIndicator).hide();
                scroller.preLoading = false;
                scroller.loading = false;
            }
        });
    }

    return this;
};