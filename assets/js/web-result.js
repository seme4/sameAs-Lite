/**
 * sameAs Lite
 * jQuery for Symbols pages
 */

var app = {
    ajaxMimeType: 'application/json', // default mime type
    outputMimeType: 'text/html', // default mime type
    page: 0, //the current page (default = 0: do not append page parameter to url)
    wrap: function ($result, pre) {
        if (pre === undefined) {
            pre = 'pre';
        }
        // do not wrap if wrap already exists
        if ($result.parent(pre).length === 0) {
            $result.wrap('<' + pre + '></' + pre + '>');
        }
    },
    unwrap: function ($result, pre) {
        if (pre === undefined) {
            pre = 'pre';
        }
        // for html table output, we do not want <pre> tags
        if ($result.parent(pre).length) {
            $result.unwrap();
        }
    },
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
    updateState: function(data) {

        // loop over the mime buttons to mark the current one
        this.markCurrent($('.label.alternate_format'), this.outputMimeType, 'data');

        // loop over the page buttons to mark the current one
        this.markCurrent($('.pagination .label.page'), this.page, 'text');

        // update the url
        //window.location.href.replace(/page=\d+/i, "?page=" + app.page);
        if (this.page) {
            window.history.pushState(data, "SameAs-Lite Result Page " + this.page, "?page=" + this.page);
        }

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
            app.ajaxMimeType = 'application/json';
        } else {
            app.ajaxMimeType = app.outputMimeType;
        }

        var $result = $('#result');

        // clear
        $result.html('');

        $.ajax({
            url: (app.page > 1 ? '?page=' + app.page : ''),
            dataType: "text",
            headers: {
                "Accept": app.ajaxMimeType
            },
            beforeSend: function(xhr) {
                xhr.overrideMimeType(app.ajaxMimeType);
            },
            success: function (data, textStatus, jObj) {

                if (data) {

                    if (app.outputMimeType !== 'text/html') {

                        $result.text(data);
                        // wrap pre tags around result (only once)
                        app.wrap($result);

                    } else {

                        // build a table from the incoming json data
                        // and replace the contents of the div container

                        data = $.parseJSON(data);
                        //console.log(data);

                        var header_values = [],
                            $header = '',
                            $body = '',
                            $row = '',
                            data_rows = [],
                            i = 0,
                            s = data.length;

                        // is this tabular data or a simple list of values?
                        if (typeof data[0] === 'string') {

                            // no headers
                            header_values = false;
                            data_rows = data;

                            // add results as an unordered list
                            $ul = $('<ul></ul>').addClass('table');
                            $.each(data_rows, function(index, row) {
                                // html special char escaping
                                row = row.replace('<', '&lt;').replace('>', '&gt;');
                                $row = $('<li></li>').append(row);
                                $ul.append($row);
                            });
                            $result.html($ul);

                        } else {

                            $table = $('<table></table>').addClass('table');

                            // if the first element is an object,
                            // then there are several levels to the array
                            // see for example analysis output

                            // get the table header from the first element
                            if (data[0]) {
                                header_values = Object.keys(data[0]);
                            }

                            // get the table rows
                            for (i = 0; i < s; i++){
                                data_rows[i] = [];
                                $.each(header_values, function() {
                                    data_rows[i].push(data[i][this]);
                                });
                            }

                            var val;

                            // add the processed data to a table
                            // headers
                            if (header_values) {
                                $header = $('<thead></thead>');
                                $header_row = $('<tr></tr>');
                                $.each(header_values, function() {
                                    val = this.replace('<', '&lt;').replace('>', '&gt;');
                                    $header_row.append('<th class="w50">' + val + '</th>');
                                });
                                $header.append($header_row);
                                $table.append($header);
                            }
                            // rows
                            $body = $('<tbody></tbody>');
                            $.each(data_rows, function(index, row) {
                                $row = $('<tr></tr>');
                                if (typeof row === 'string') {
                                    row = row.replace('<', '&lt;').replace('>', '&gt;');
                                    $row.append('<td>' + row + '</td>');
                                } else {
                                    $.each(row, function() {
                                        val = this.replace('<', '&lt;').replace('>', '&gt;');
                                        $row.append('<td>' + val + '</td>');
                                    });
                                }
                                $body.append($row);
                            });
                            $table.append($body);

                            $result.html($table);

                        }

                        // for html table output, we do not want <pre> tags
                        app.unwrap($result);

                    }

                }

                //always update the buttons and the url to reflect the page we are on
                app.updateState(data);

            },//end success()
            error: function (jObj) {

                var data = jObj.responseText;

                app.updateState(data);

                app.unwrap($result);

                switch (app.ajaxMimeType) {

// TODO: add other mime types here










                    case 'application/rdf+xml':
                    case 'text/turtle':

                        data = JSON.parse(data);
                        data = JSON.stringify(data);
                        data = data.replace("\\n", '');
                        data = data.replace('\n', '');
                        data = data.replace("\n", "<br />");

                        break;

                    default:

                        data = data.replace("\n", "<br />");

                        break;

                }//end switch

                $result.html(data);

            }//end error()
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
