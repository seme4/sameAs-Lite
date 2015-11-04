/**
 * sameAs Lite
 * jQuery for Symbols pages
 */

var app = {
    ajaxMimeType: 'application/json', // default mime type
    outputMimeType: 'text/html', // default mime type
    page: 1, //the current page (default = 1)
    markCurrent: function ($items, current, attrType) {
        $items.removeClass('current');
        $items.each(function(){
            if (attrType === 'data') {
                if ($(this).data('mime') == current) {
                    $(this).addClass('current');
                    return false; //break
                }
            } else {
                if ($(this).text() == app.page) {
                    $(this).addClass('current');
                    return false; //break
                }
            }
        });
    },
    getPageContents: function() {
        // issue an ajax request to the current page for the selected mime type
        // then replace the content of the page with the result

        // if this is a call with mime type text/html
        // we run into the problem that it would load
        // the full wepage instead of a fragment
        // so we cheat and execute a call for json
        // then process the result on the client side
        // to update the table on the page
        if (app.outputMimeType === 'text/html') {
            app.ajaxMimeType = 'text/json';
        } else {
            app.ajaxMimeType = app.outputMimeType;
        }

        $.ajax({
            url: (app.page > 1 ? '?page=' + app.page : ''),
            headers: {
                "Accept": app.ajaxMimeType
            },
            beforeSend: function(xhr) {
                xhr.overrideMimeType(app.ajaxMimeType);
            },
            success: function (data, textStatus, jObj) {

                if (jObj.status == 200) {

                    $result = $('#result');

                    if (data) {
                        // clear
                        // $result.html("");

                        if (app.outputMimeType !== 'text/html') {

                            $result.text(data);
                            // wrap pre tags around result (only once)
                            if ($result.parent('pre').length === 0) {
                                $result.wrap('<pre></pre>');

                            }
                        } else {
                            // build a table from the incoming json data
                            // and replace the contents of the div container

                            data = $.parseJSON(data);
                            //console.log(data);

                            var $table = $('<table></table>').addClass('table'),
                                header_values = [],
                                $header = '',
                                $body = '',
                                $row = '';
                            // get the table header from the first element
                            if (data[0]) {
                                header_values = Object.keys(data[0]);
                            }

                            var data_rows = [],
                                i = 0,
                                s = data.length;
                            for (i = 0; i < s; i++){
                                data_rows[i] = [];
                                $.each(header_values, function() {
                                    data_rows[i].push(data[i][this]);
                                });
                            }

                            // add the processed data to a table
                            // headers
                            $header = $('<thead></thead>');
                            $header_row = $('<tr></tr>');
                            $.each(header_values, function() {
                                $header_row.append('<th class="w50">' + this + '</th>');
                            });
                            $header.append($header_row);
                            $table.append($header);
                            // rows
                            $body = $('<tbody></tbody>');
                            $.each(data_rows, function(index, row) {
                                $row = $('<tr></tr>');
                                $.each(row, function() {
                                    $row.append('<td>' + this + '</td>');
                                });
                                $body.append($row);
                            });
                            $table.append($body);

                            $('#result').html('');

                            $('#result').html($table);
                        }

                        // loop over the mime buttons to mark the current one
                        app.markCurrent($('.label.alternate_format'), app.ajaxMimeType, 'data');

                        // loop over the page buttons to mark the current one
                        app.markCurrent($('.pagination .label.page'), app.page, 'text');

                        // update the url
                        //window.location.href.replace(/page=\d+/i, "?page=" + app.page);
                        window.history.pushState(data, "SameAs-Lite Result Page " + app.page, "?page=" + app.page);
                    }

                } else {
                    // TODO
                    console.log(jObj);
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
        app.outputMimeType = $(this).data('mime');

        app.getPageContents();
    });

    // ajax pagination
    $(".pagination .label.page").click(function (e) {
        e.preventDefault();
        e.stopPropagation();

        var button_text = $(this).text();

        if (app.page == button_text) {
            return; // we do not need to get the results for the already loaded page
        }

        app.page = button_text;
        app.getPageContents();
    });

});

// vim: set filetype=javascript expandtab tabstop=4 shiftwidth=4:
