/**
 * sameAs Lite
 * jQuery for Symbols pages
 */

$(document).ready(function() {

    $(".label.alternate_format").click(function (e) {
        e.preventDefault();
        e.stopPropagation();

        //get the required format
        var mime = $(this).data('mime');

        // issue the request with the selected mime type


        // TODO
        // needs to be ajax
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

                if (data) {
                    $('#result').html(data);
                }

            },
          dataType: "text"
        });


    });


});

// vim: set filetype=javascript expandtab tabstop=4 shiftwidth=4:
