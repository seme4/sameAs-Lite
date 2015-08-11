/**
 * sameAs Lite
 * jQuery for API pages
 */

$(document).ready(function() {

    // Loads and opens the panel stored in sessionStorage
    function loadFromStorage(){
        // Fetch from sessionStorage
        var panelId = sessionStorage.getItem("lastApiPanel"),
            panel = (panelId)?$("#" + panelId):null;
        if(panel && panel.collapse){
            panel.collapse();
            panel.get(0).scrollIntoView();
        }
    }

    loadFromStorage();

    // Saves the given panelId to sessionStorage
    function saveToStorage(panelId){
        if("sessionStorage" in window){
            sessionStorage.setItem("lastApiPanel", panelId);
        }
    }



    $('form.api').submit(function(e) {
        var t = $(this),

            template = t.data('url');

        if (template !== null){

            //  insert values into template
            //  TODO - check for and handle empty/missing values?
            fields = t.find('input[type="text"]');
            for (i = 0; i < fields.length; i++) {
                f = $(fields[i]);
                template = template.replace(
                    '{' + f.attr('name') + '}',
                    encodeURIComponent(f.val())
                );
            }

            //  apply new action before submitting form
            t.attr('action', template);

        }


        // Set the sessionStorage value for this action (IE8+)
        var panelId = t.closest(".collapse").attr("id");
        saveToStorage(panelId);
    });

    $(".get-btn").click(function(e){
        // Set the sessionStorage value for this action (IE8+)
        var panelId = $(this).closest(".collapse").attr("id");
        saveToStorage(panelId);
    });


});

// vim: set filetype=javascript expandtab tabstop=4 shiftwidth=4:
