jQuery(document).ready(function ($) {
   
    // do something after jQuery has laoded
    ssp_debug('private js script loaded!');

    //hint: displats a message and data in the console debugger
    function ssp_debug(msg, data) {
        try {
            console.log(msg);

            if(typeof data !== 'undefined'){
                console.log(data);
            }

        } catch (e) {
            
        }
    }



    // Setup our wp ajax URL
    var wpajax_url = document.location.protocol + '//' + document.location.host + '/wp-admin/admin-ajax.php';
    
    // bind custom function to survey form submit event
    $(document).on('change', '.ssp-stats-admin-page [name="ssp_survey"]', function (e) {


        var surver_id = $('option:selected', this).val();

        ssp_debug('selected survey', surver_id);

        $stats_div = $('.ssp-survey-stats', '.ssp-stats-admin-page');

        // submit the chosen item via ajax
        $.ajax({
            cache: false,
            method: 'post',
            url: wpajax_url + '?action=ssp_ajax_get_stats_html',
            dataType: 'json',
            data:{
                surver_id: surver_id
            },
            success:function (response) {
                // return response in console for debugging....
                ssp_debug(response);
                
                if(response.status){
                    // update the html of the current li
                    $dl.replaceWith(response.html);
                    // hide survey content message
                    $stats_div.replaceWith(response.html);
                }else{
                    alert(response.message);
                }
            },
            error:function (jqXHR, textStatus, errorThrown) {
                // return response in console for debugging....
                ssp_debug('error', jqXHR);
                ssp_debug('textStatus', textStatus);
                ssp_debug('errorThrown', errorThrown);
            }
        })
    });

});