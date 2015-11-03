/**
 * sameAs Lite
 * jQuery for Symbols pages
 */

$(document).ready(function() {

    $("#alternate_mime_options .label.alternate_format").click(function (e) {
        e.preventDefault();
        e.stopPropagation();

        //get the required format
        var mime = $(this).data('mime');

        // issue an ajax request for the selected mime type
        // then replace the content of the page with the result
        $.ajax({
            url: '',
            headers: {
                "Accept": mime
            },
            beforeSend: function(xhr) {
                xhr.overrideMimeType(mime);
            },
            success: function (data, textStatus) {

                $result = $('#result');

                if (data) {
                    $result.html("");
                    $result.text(data);
                    console.log($result.parent('pre'));
                    if ($result.parent('pre').length === 0) {
                        $result.wrap('<pre></pre>');
                    }
                }

            },
          dataType: "text"
        });


    });


});

// vim: set filetype=javascript expandtab tabstop=4 shiftwidth=4:
