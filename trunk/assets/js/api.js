/**
 * sameAs Lite
 * jQuery for API pages
 */

$(document).ready(function() {

    $('form.api').submit(function(e) {

        template = $(this).data('url');
        if (template == null) return;

        //  insert values into template
        //  TODO - check for and handle empty/missing values?
        fields = $(this).find('input[type="text"]');
        for (i = 0; i < fields.length; i++) {
            f = $(fields[i]);
            template = template.replace(
                '{' + f.attr('name') + '}',
                encodeURIComponent(f.val())
            );
        }

        //  apply new action before submitting form
        $(this).attr('action', template);

    });
});

// vim: set filetype=javascript expandtab tabstop=4 shiftwidth=4:
