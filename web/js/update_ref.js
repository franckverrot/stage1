(function($, window) {
    var prepare = [
        'ref-kill-form',
        'ref-cancel-form',
        'ref-schedule-form',
        'ref-status',
        'ref-show-link'
    ];

    var tpl = {};

    for (i in prepare) {
        var tpl_id = prepare[i];
        if ($('#tpl-' + tpl_id).length > 0) {
            // console.log('preparing template', tpl_id);
            tpl[tpl_id] = Mustache.compile($('#tpl-' + tpl_id).text());
        }
    }

    window.do_update_ref = function(build) {
        if (undefined !== build.status) {
            update_ref(build.normRef, 'status', function(el) {
                if (el.length == 0 && undefined != tpl['ref-status']) {
                    $('#ref-' + build.normRef + ' .ctn-status').html(tpl['ref-status']({
                        name: build.ref,
                        normName: build.normRef,
                        hash: build.hash,
                        status: build.status,
                        status_label: build.status_label,
                        status_label_class: build.status_label_class
                    }));
                } else {
                    el.data('status', build.status).removeClass().addClass('label label-' + build.status_label_class).html(build.status_label);
                }
            });
        }

        if (null != build.url && build.url.length > 0) {
            update_ref(build.normRef, 'link', function(el) {
                if (el.data('template')) {
                    el.html(Mustache.render(el.data('template'), { build_url: build.url }));
                } else {
                    el.html('<a href="' + build.url + '">' + build.url + '</a>');
                }
            });
        }

        update_ref(build.normRef, 'schedule-form', function(el) {
            if ($('button i', el).hasClass('fa-refresh')) {
                $('button', el).removeClass().addClass('btn btn-small btn-success').html('<i class="fa fa-check"></i>');
                setTimeout(function() { el.remove(); }, 1000);                    
            } else {
                el.remove();
            }
        });

        update_ref(build.normRef, 'kill-form', function(el) {
            if ($('button i', el).hasClass('fa-refresh')) {
                $('button', el).removeClass().addClass('btn btn-small btn-success').html('<i class="fa fa-check"></i>');
                setTimeout(function() { el.remove(); }, 1000);                    
            } else {
                el.remove();
            }
        });

        update_ref(build.normRef, 'cancel-form', function(el) {
            if ($('button i', el).hasClass('fa-refresh')) {
                $('button', el).addClass('btn btn-small btn-success').html('<i class="fa fa-check"></i>');
                setTimeout(function() { el.remove(); }, 1000);                    
            } else {
                el.remove();
            }            
        });

        if (build.kill_url && tpl['ref-kill-form']) {
            update_ref(build.normRef, 'form-container', function(el) {
                el.append(tpl['ref-kill-form']({
                    name: build.ref,
                    normName: build.normRef,
                    kill_url: build.kill_url
                }));
            });
        }

        if (undefined !== build.cancel_url) {
            update_ref(build.normRef, 'form-container', function(el) {
                el.append(tpl['ref-cancel-form']({
                    name: build.ref,
                    normName: build.normRef,
                    cancel_url: build.cancel_url
                }));
            });            
        }
    
        if (build.schedule_url && tpl['ref-schedule-form']) {
            update_ref(build.normRef, 'form-container', function(el) {
                el.append(tpl['ref-schedule-form']({
                    name: build.ref,
                    normName: build.normRef,
                    schedule_build_url: build.schedule_url,
                    data: [
                        { name: 'ref', value: build.ref },
                    ]
                }));
            });
        }

        if (build.show_url && tpl['ref-show-link']) {
            update_ref(build.normRef, 'show-link', function(el) {
                el.html(tpl['ref-show-link']({
                    show_url: build.show_url
                }));
            });
        }
    };

    function update_ref(norm_name, type, callback) {
        // console.log('update_ref', norm_name, type);
        callback($('#ref-' + norm_name + '-' + type));
    }
})(jQuery, window);