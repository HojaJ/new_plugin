jQuery(document).ready(function ($) {
   
    // do something after jQuery has laoded
    ssp_debug('public js script loaded!');

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
    $(document).on('submit', '.ssp-survey-form', function (e) {
        
        // prevent form from submitting normally
        e.preventDefault();

        $form = $(this);
        $survey = $form.closest('.ssp-survey');

        // get selected radio button
        $selected = $('input[name^="ssp_question_"]:checked', $form);

        // split field name into array
        var name_arr = $selected.attr('name').split('_');
        // get the survey id from the last item in name array
        var survey_id = name_arr[2];
        // get the response id from the value of the selected item
        var response_id = $selected.val();

        var data = {
            _wpnonce: $('[name="_wpnonce"]', $form).val(),
            _wp_http_referer: $('[name="_wp_http_referer"]', $form).val(),
            survery_id: survey_id,
            response_id: response_id
        };

        ssp_debug('data', data);

        // get the closest dl.ssp-question eleemnt
        $dl = $selected.closest('dl.ssp-question');


        // submit the chosen item via ajax
        $.ajax({
            cache: false,
            method: 'post',
            url: wpajax_url + '?action=ssp_ajax_save_response',
            dataType: 'json',
            data:data,
            success:function (response) {
                // return response in console for debugging....
                ssp_debug(response);
                
                if(response.status){
                    // update the html of the current li
                    $dl.replaceWith(response.html);
                    // hide survey content message
                    $('.ssp-survey-footer', $survey).hide();
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


    })

});