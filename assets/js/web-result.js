/**
 * sameAs Lite
 * jQuery for Symbols pages
 */

var app = {
    mimeType: 'text/html', // default mime type
    page: 1, //the current page (default = 1)
    getPageContents: function() {
        // issue an ajax request to the current page for the selected mime type
        // then replace the content of the page with the result
        $.ajax({
            url: (app.page > 1 ? '?page=' + app.page : ''),
            headers: {
                "Accept": app.mimeType
            },
            beforeSend: function(xhr) {
                xhr.overrideMimeType(app.mimeType);
            },
            success: function (data, textStatus) {

                $result = $('#result');

                if (data) {
                    // clear
                    // $result.html("");
                    $result.text(data);
                    // wrap pre tags around result (only once)
                    if ($result.parent('pre').length === 0) {
                        $result.wrap('<pre></pre>');
                    }

                    var $items = [];
                    
                    // loop over the mime buttons to mark the current one
                    $items = $('.label.alternate_format');
                    $items.removeClass('current');
                    $items.each(function(){
                        if ($(this).data('mime') == app.mimeType) {
                            $(this).addClass('current');
                            return false; //break
                        }
                    });

                    // loop over the page buttons to mark the current one
                    $items = $('.pagination .label.page');
                    $items.removeClass('current');
                    $items.each(function(){
                        if ($(this).text() == app.page) {
                            $(this).addClass('current');
                            return false; //break
                        }
                    });

                    // mess with the url
                    //window.location.href.replace(/page=\d+/i, "?page=" + app.page);

                    window.history.pushState(data, "SameAs-Lite Result Page " + app.page, "?page=" + app.page);

                }

            },
          dataType: "text"
        });
    }
};

$(document).ready(function() {

    // mime type ajax button
    $("#alternate_mime_options .label.alternate_format").click(function (e) {
        e.preventDefault();
        e.stopPropagation();

        //get the required format and remember the mime type
        app.mimeType = $(this).data('mime');

        app.getPageContents();
    });

    // ajax pagination
    $(".pagination .label.page").click(function (e) {
        e.preventDefault();
        e.stopPropagation();

        app.page = $(this).text();

        app.getPageContents();
    });

});

// vim: set filetype=javascript expandtab tabstop=4 shiftwidth=4:
