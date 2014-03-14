(function() {
    console.log('STAGE1: detected project ' + stage1.project);

    // stolen from http://dustindiaz.com/smallest-domready-ever
    window.s1_ready = function(f) {
        /in/.test(document.readyState) ? setTimeout('s1_ready(' + f + ')', 9) : f();
    };

    console.log('STAGE1: injecting zepto');

    script = document.createElement('script');
    script.type = 'text/javascript';
    script.src = '//stage1.dev/js/vendor/zepto.min.js';

    document.head.appendChild(script);

    s1_ready(function() {
        console.log('STAGE1: setting up stage1 stuff...');

        $(document.head).append('<link rel="stylesheet" type="text/css" href="//stage1.dev/css/inject.css" />');
        $(document.head).append('<script type="text/javascript" src="//stage1.dev/js/DomSelector.js"></script>');

        $(document.body).append('<div id="stage1-container"></div>');

        var sidebarState = 'closed';

        $('#stage1-container').load('//stage1.dev/overlay', function() {
            $('[data-content]').each(function(i, el) { $(el).html(stage1[$(el).data('content')]); });
            $('[data-value]').each(function(e, el) { $(el).val(stage1[$(el).data('value')]) });

            $logo = $('#stage1-logo');
            $sidebar = $('#stage1-sidebar');

            $sidebar.on('mouseover', function() { $(this).css('opacity', 1); });
            $sidebar.on('mouseout', function() { $(this).css('opacity', .5); });

            $('#stage1-logo').on('click', function() {
                if (sidebarState === 'closed') {
                    var logo_right = $sidebar.width();
                    var sidebar_right = 0;
                    var sidebar_opacity = 1;
                    $('#stage1-issue-title').focus();
                    sidebarState = 'open';
                } else {
                    var logo_right = 0;
                    var sidebar_right = -$sidebar.width();
                    var sidebar_opacity = 0.5;
                    $('#stage1-issue-title').blur();
                    sidebarState = 'closed';
                }

                $logo.animate({ right: logo_right }, 'fast');                    
                $sidebar.animate({ right: sidebar_right, opacity: sidebar_opacity }, 'fast', null);
            });

            $('#stage1-form').submit(function(event) {
                var $submit = $('#stage1-submit');
                var form = this;

                try {
                    $submit.attr('disabled', true).html('Sending...');

                    $.post(this.action, $(this).serialize(), function(res) {
                        console.log(res);
                        $submit.attr('disabled', false).html('Create issue');
                        form.reset();
                    });
                } catch (e) {
                    $submit.attr('disabled', false).html('Create issue');
                }

                event.preventDefault();
            });

            var selector = new DomSelector($, function(element) {
                this.lock();
                $('#stage1-issue-title').focus();
                $('#stage1-sidebar').css('opacity', '');
            });
        });
    });
})();