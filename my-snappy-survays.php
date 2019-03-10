<?php
/**
 * Plugin Name: My Snappy Survays
 * Plugin URI:  https://example.com/plugins/the-basics/
 * Description: Basic WordPress Plugin
 * Version:     1.0
 * Author:      Hodja
 * Author URI:  https://hojaj.github.com/
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: my-snappy-survays
 * Domain Path: /languages
 */


 /**  Hooks */
 // hint: register custom admin menus and pages
add_action('admin_menu', 'ssp_admin_menus');

// hint: plugin activation
register_activation_hook(__FILE__, 'ssp_activate_plugin');

//hint: register shortcodes
add_action('init', 'ssp_register_shortcodes');

//hint: laod external scripts
add_action('admin_enqueue_scripts', 'ssp_admin_scripts');
add_action('wp_enqueue_scripts', 'ssp_public_scripts');


//hint: register ajax functions
add_action('wp_ajax_ssp_ajax_save_response', 'ssp_ajax_save_response'); //admin user
add_action('wp_ajax_nopriv_ssp_ajax_save_response', 'ssp_ajax_save_response'); //Website user
add_action('wp_ajax_ssp_ajax_get_stats_html', 'ssp_ajax_get_stats_html'); //admin user



// hint: custom admin columns
add_action('manage_edit-ssp_survey_columns','ssp_survey_column_headers');

// hint: custom admin columns
add_action('manage_ssp_survey_posts_custom_column','ssp_survey_column_data', 1, 2);





/** SHORTCODES */
// hint: registers custom shortcodes for this plugin
function ssp_register_shortcodes()
{
    // hint: [ssp_survey id="123"]
    add_shortcode('ssp_survey', 'ssp_survey_shortcode');
}

//hint: displays a survey
function ssp_survey_shortcode($args, $content=''){
    // setup our return vairable
    $output = '';


    try {
        // begin building our output html
        $output = '<div class="ssp ssp-survey">';

        // get the survey id
        $survey_id = (isset($args['id'])) ? (int)$args['id'] : 0;

        // get the survey object
        $survey = get_post($survey_id);

        // IF the survey id not a valid ssp_survey post, return a message
        if( !$survey_id || $survey->post_type !== 'ssp_survey' ):
            $output = '<p>The requested survey does not exist.</p>';

        else:
            // build form html
            $form = '';

            if(strlen($content)):
                $form = '
                    <div class="ssp-survey-content">
                    '. wpautop($content) .'
                    </div>
                ';
            endif;

            $submit_button = '';

            $responses = ssp_get_survey_responses( $survey_id );

            if( !ssp_question_is_answered( $survey_id ) ):
                $submit_button = '
                <div class="ssp-survey-footer">
                    <p><em>Submit your response to see the results of  all '. $responses .'participants surveyed. </em></p>
                    <p class="ssp-input-container ssp-submit">
                        <input type="submit" name="ssp_submit" value="Submit Your Response" />  
                    </p>
                </div>
                ';
            endif;

            $nounce = wp_nonce_field( 'ssp-save-survey-submission_'. $survey_id,'_wpnonce',true,false );

            $form .= '
                <form id="survey_'. $survey_id .'" class="ssp-survey-form">
                    '. $nounce .'
                    '. ssp_get_question_html($survey_id) . $submit_button .'
                </form>
            ';
            // Append form html to $output
            $output .= $form;

        endif;
        $output .= '</div>';
    } catch (Exception $e) {
        
    }
    //return output
    return $output;
}





/** CUSTOM POST TYPE */
// ssp_survey
include_once(plugin_dir_path(__FILE__) . '/cpt/ssp_survey.php');





/**HELPERS */
// hint: returns html for survey question
function ssp_get_question_html($survey_id, $force_results=false){
    $html = '';

    // get the survey post object
    $survey = get_post($survey_id);

    // IF $survey is a valid ssp_survey post type...
    if($survey->post_type == 'ssp_survey'):
        // get the survey question text
        $question_text = $survey->post_content;

         // setup our default question options
         $question_opts = array(
             'Strongly Agree' => 5,
             'Somewhat Agree' => 4,
             'Nuetral' => 3,
             'Somewhat Disagree' => 2,
             'Strongly Disagree' => 1
        );

            // Check if the current user has already answered this survey question
        $answered = ($force_results) ? true : ssp_question_is_answered($survey_id);

        // default complete class is blank
        $complete_class =  '';
        
        if( !$answered ):
            // setup our inputs html
            $inputs = '<ul class="ssp-question-options">';

            // loop over all the $question_opts
            foreach ($question_opts as $key => $value):

                $stats = ssp_get_response_stats($survey_id, $value);

                // append over all the $question_opts
                $inputs .= '<li><label><input type="radio" name="ssp_question_'. $survey_id .'" value="'. $value .'"/>'.$key.'</label></li>';

            endforeach;    
            $inputs .= '</ul>';

        else:
            // survey is complete, add a real complete class
            $complete_class = ' ssp-question-complete';

            $inputs = 'Thank you for completing our survey';
        endif;    

        $html .= '
            <dl id="ssp_'. $survey_id .'_question" class="ss-question '. $complete_class .'">
                <dt>' .$question_text. '</dt>
                <dd>' . $inputs . '</dd>
            </dl>
            ';
    endif;
    return $html;
}

//hint: returns true or false depending on
// whether or not the current user has answered the survey
function ssp_question_is_answered($survey_id){

    global $wpdb;

    // setup default return value
    $return_value  = false;

    try {
        // get user ip address
        $ip_address = ssp_get_client_ip();
        //ssp_debug( 'ip address', $ip_address );

        // sql to check if this user has completed the survey
        $sql = "
            SELECT response_id FROM {$wpdb->prefix}ssp_survey_responses 
            WHERE survey_id = %d AND ip_address = %s
        ";

        // prepare query
        $sql = $wpdb->prepare($sql, $survey_id, $ip_address);

        // run query, returns entry id if successful
        $entry_id = $wpdb->get_var($sql);

        // IF query worked and entry
        if($entry_id !== NULL):
            // Set our return value to the entry_id
            $return_value = $entry_id;
        endif;
        


    } catch (Exception $e) {
        
    }
    // return value 
    return $return_value;

}

// hint: returns json string and exists php processes
function ssp_return_json($php_array){
    // encode result as json string
    $json_result = json_encode( $php_array );

    // return result
    die($json_result);

    // stop all other processing
    exit;
}

// hint: makes it's best attempt to get the ip address of the current user
function ssp_get_client_ip(){
    $ipaddress = '';
    if (getenv('HTTP_CLIENT_ID')):
        $ipaddress = getenv('HTTP_CLIENT_ID');
    elseif(getenv('HTTP_X_FORWARDED_FOR')):
        $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
    elseif(getenv('HTTP_X_FORWARDED')):
        $ipaddress = getenv('HTTP_X_FORWARDED');
    elseif(getenv('HTTP_FORWARDED_FOR')):
        $ipaddress = getenv('HTTP_FORWARDED_FOR');
    elseif(getenv('HTTP_FORWARDED')):
        $ipaddress = getenv('HTTP_FORWARDED');
    elseif(getenv('REMOTE_ADDR')):
        $ipaddress = getenv('REMOTE_ADDR');    
    else:
        $ipaddress = 'UNKNOWN';    
    endif;
    return $ipaddress;
}

// hint: writes and output to the browser and runs kills php processes
function ssp_debug($msg = '', $data=false, $die=true){

    echo '<pre>';

    if(strlen($msg)):
        echo $msg . ' <br/>';
    endif;

    if($data !== false):
        var_dump($data);
    endif;
    echo '</pre>';
    if($die) die();
}

// hint: get's the statics for a survey response
function ssp_get_response_stats( $survey_id, $response_id ){

    // setup default return variable
    $stats = array(
        'percentage' => '0%', 
        'votes'      => 0
    );

    try {
        // get responses for this item
        $item_responses = ssp_get_item_responses($survey_id, $response_id);

        // get total responses for this survey
        $survey_responses = ssp_get_survey_responses($survey_id);

        if( $survey_responses && $item_responses ):
            $stats = array(
                'percentage' => ceil(($item_responses/$survey_responses)*100) . '%', 
                'votes'     => $item_responses
            );

        endif;

    } catch (Exception $e) {
        // php errort
        ssp_debug('ssp_get_response_stats exception', $e);
    }

    // return stats;
    return $stats;
}
//hint
function ssp_get_item_responses($survey_id, $response_id) {
    global $wpdb;

    $item_responses = 0;
    try {
        // sql to check if this user has completed the survey
        $sql = "
            SELECT count(id) AS total FROM {$wpdb->prefix}ssp_survey_responses
            WHERE survey_id = %d AND response_id = %d
        ";

        // prepare query
        $sql = $wpdb->prepare($sql, $survey_id, $response_id);

        //run query 
        $item_responses = $wpdb->get_var($sql);


    } catch (Exception $th) {

        // php error
        ssp_debug('ssp_get_item_responses php error', $e->getMessage());
    }
    return $item_responses;
}




function ssp_get_survey_responses($survey_id){
    global $wpdb;

    $item_responses = 0;
    try {
        // sql to check if this user has completed the survey
        $sql = "
            SELECT count(id) AS total FROM {$wpdb->prefix}ssp_survey_responses
            WHERE survey_id = %d
        ";

        // prepare query
        $sql = $wpdb->prepare($sql, $survey_id);

        //run query 
        $survey_responses = $wpdb->get_var($sql);


    } catch (Exception $th) {

        // php error
        ssp_debug('ssp_get_survey_responses php error', $e->getMessage());
    }
    return $survey_responses;
}


function ssp_get_submission_received( $survey_id = 0 ) {

    global $wpdb;

    // Set default return value
    $submission_received = 0;

    $today = date('Y-m-d');
    $today .= ' 00:00:00';


    try {

        // UF id is provided
        if($survey_id):

            // sql to check if this user has completed the survey
            $sql = "
            SELECT count(id) AS total FROM {$wpdb->prefix}ssp_survey_responses
            WHERE updated_at >= '{$today}'
            AND survey_id = %d
            ";

            // prepare query
            $sql = $wpdb->prepare($sql, $survey_id);

        else:
                // sql to check if this user has completed the survey
            $sql = "
                SELECT count(id) AS total FROM {$wpdb->prefix}ssp_survey_responses
                WHERE updated_at >= '{$today}'
            ";

        endif;

        // run query
        $submission_received = (int)$wpdb->get_var($sql);
    } catch (Exception $th) {

        // php error
        ssp_debug('ssp_get_submission_received php error', $e->getMessage());
    }
    return $submission_received;
}





 /**  ADMIN PAGES */
 // hint: this page page explains what the plugin is about
 // and provides a snapshot of surbey participation for the day
function ssp_welcome_page(){
    $submission_received = ssp_get_submission_received();

    $submission_received_msg = 'No submissions reveived today... yet!';

    if($submission_received) :
        $submission_received_msg = 'Wohoo!' . $submission_received_msg .'submissions reveived today.';
    endif;

    $output = '
        <div class="wrap ssp-welcome-admin-page">
            <h2>Snappy Surveys</h2>
            <h3>'. $submission_received_msg .'</h3       
            <ol>
                <li>Get to know audience.</li>
                <li><a href="'. admin_url('post-new.php?post_type=ssp_survey') .'">Create simple surveys</a> that capture annonymous data.</li>
                <li><a href="'. admin_url('admin.php?page=ssp_stats_page') .'">See insightfull statisctics</a> from your surveys.</li>
            </ol>
        </div>
    ';

    echo $output;
} 

// hint: this page page dynamic survey statictics
function ssp_stats_page(){
    
    $surveys = get_posts(array(
        'post_type' => 'ssp_survey',
        'post_status' => array('publish', 'draft'),
        'posts_per_page' => -1,
        'orderby'   => 'post_title',
        'order'     => 'ASC'
    ));

    $selected_survey_id = (isset($_GET['survey_id'])) ? (int)$_GET['survey_id'] : 0;

    if(count($surveys)):

        // build form select html
        $select_html = '<label>Selected Survey:</label><select name="ssp_survey"><option> - Select One - </option>';

        foreach ($surveys as $survey) {
            // determine selected attribute for this option
            $selected = '';
            if($survey->ID == $selected_survey_id):
                $selected = ' selected="selected"';
            endif;

            // append option to select html
            $select_html .= '<option value="'. $survey->ID .'"'. $selected .'>'. $survey->post_title .'</option>';
        }
        // close select input
        $select_html .= '</select>';
    
    else:
        // if no surveys
        $select_html .= 'You don\'t have any surveys yet! Why not <a href="'. admin_url('post-new.php?post_type=ssp_survey') .'">Create a New Survey?</a>';

    endif;
    
    // get stats html
    $stats_html = '<div class="ssp-survey-stats"></div>';

    if( $selected_survey_id):
        $stats_html = ssp_get_stats_html($selected_survey_id);
    endif;

    $output = '
        <div class="wrap ssp-stats-admin-page">
            <h2>Survey Statistics</h2>
            <p>
               '. $select_html .' 
            </p>
            '. $stats_html .'
        </div>
    ';

    echo $output;
} 




function ssp_ajax_get_stats_html(){

    $result = array(
        'status' => 0, 
        'message' => 'Could not get stats html',
        'html' => ''
    );

    // get survey id from Get scope
    $survey_id = (isset($_POST['survey_id'])) ? (int)$_POST['survey_id'] : 0;

    // IF survey success resul
    if($survey_id):
        // build success result
        $result = array(
            'status' => 2, 
            'message' => 'Stats html retrieved successfully',
            'html' => ssp_get_stats_html($survey_id)
        );  
    endif;

    // return 
    ssp_return_json($result);
}

function ssp_get_stats_html($survey_id){
    $ouput = '<div class="ssp-survey-stats"></div>';

    if($survey_id):
        $question_html = ssp_get_question_html($survey_id, true);
        $responses = ssp_get_survey_responses($survey_id);
        $submission_received = ssp_get_submission_received($survey_id);
    endif;

    //build output
    $output = '
        <div class="ssp-survey-stats">
            '. $question_html .'
            <p>'. $responses .' total participants</p>
            <p>'. $submission_received .' Submissions reveived today.</p>
        </div>
    ';
    return $output;
}
















/**FILTERS */
function ssp_admin_menus()
{
    /** main menu */
    $top_menu_item = 'ssp_welcome_page';

    add_menu_page('', 'Snappy Surveys', 'manage_options', $top_menu_item,$top_menu_item, 'dashicons-chart-bar' );

    /** Sub Menu */

    // Welcome
    add_submenu_page($top_menu_item, '', 'Welcome', 'manage_options', $top_menu_item, $top_menu_item);

    // Surveys
    add_submenu_page($top_menu_item, '', 'Surveys', 'manage_options', 'edit.php?post_type=ssp_survey');

    // Stats
    add_submenu_page($top_menu_item, '', 'Stats', 'manage_options', 'ssp_stats_page', 'ssp_stats_page');
}

function ssp_survey_column_headers( $columns ){
    // creating custom column header data
    $columns = array(
        'cb' => '<input type="checkbox"/>', 
        'title' => __('Survey'),
        'responses' => __('Responses'),
        'shortcode' => __('Shortcode'),
    );

    // returning new columns
    return $columns;
}


function ssp_survey_column_data($column, $post_id){
    // setup our return text
    $output = '';

    switch ($column) {
        case 'responses':
            $stats_url = admin_url('admin.php?page=ssp_stats_page&survey_id=' . $post_id);
            $responses = ssp_get_survey_responses( $post_id );
            $output .= '<a href="'. $stats_url .'" title="See Survey Statistics">'. $responses .'</a>';
            break;
        case 'shortcode':
            $shortcode = '[ssp_survey_id="'. $post_id .'"]';
            $output .= '<input onClick="this.select();" type="text" value="'. htmlspecialchars($shortcode) .'" readonly />';
            break;
        
    }
    echo $output;

}



/** EXTERNAL SCRIPTS */
// hint: loads external files into wordpress ADMIN
function ssp_admin_scripts()
{
    // register scripts with WordPress's internal library
    wp_register_script('ssp-is-private-scripts', plugins_url('/js/private/ssp.js', __FILE__), array('jquery'), '', true);

    // add to que of scripts that get loaded into every admin page
    wp_enqueue_script('ssp-is-private-scripts');
}



// hint: loads external files into PUBLIC WEBSITE
function ssp_public_scripts()
{
    // register scripts with WordPress's internal library
    wp_register_script('ssp-js-public', plugins_url('/js/public/ssp.js', __FILE__), array('jquery'), '', true);
    wp_register_style('ssp-css-public', plugins_url('/css/public/ssp.css', __FILE__) );

    // add to que of scripts that get loaded into every admin page
    wp_enqueue_script('ssp-js-public');
    wp_enqueue_style('ssp-css-public');
}


function ssp_save_response($survey_id, $response_id){
    global $wpdb;

    $return_value = false;

    try {

        $ip_address = ssp_get_client_ip();

        // get question post object
        $survey = get_post($survey_id);

        if($survey->post_type == 'ssp_survey'):

            // Get current timastamp
            $now = new DateTime();
            $ts = $now->format('Y-m-d H:i:s');

            // query sql
            $sql  = "
                INSERT INTO {$wpdb->prefix}ssp_survey_responses (ip_address, survey_id, response_id,created_at )
                VALUES ( %s, %d, %d, %s )
                ON DUPLICATE KEY UPDATE survey_id = %d
            ";

            // prepare query
            $sql = $wpdb->prepare($sql, $ip_address, $survey_id, $response_id, $ts, $survey_id);

            // run query
            $entry_id = $wpdb->query($sql);

            // IF response saved successfully.... 
            // if( $entry_id ):
            if($entry_id !== NULL):
                // return true
                $return_value = true;
            endif;

        endif;
        

    } catch (Exception $e) {
        ssp_debug('ssp_save_response php error', $e->getMessage());
    }
    return $return_value;
}








/**ACTIONS */
// hint: installs custom plugin database tables
function ssp_create_plugin_tables(){
    global $wpdb;

     // setup return value
     $return_value = false;

     try {
        // run some code
        $charset_collate = $wpdb->get_charset_collate();

        // $wpdb->prefix returns the custom database prefix
        // originally setup in your wp-config.php
        
        // sql for our custom table creation
        $sql = "CREATE TABLE {$wpdb->prefix}ssp_survey_responses (
            id mediumint(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            ip_address varchar(32) NOT NULL,
            survey_id mediumint(11) UNSIGNED NOT NULL,
            response_id mediumint(11) UNSIGNED NOT NULL,
            created_at TIMESTAMP DEFAULT '1970-01-02 00:00:00',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE INDEX ix (ip_address, survey_id)
        ) $charset_collate;";
        
        // make sure we include wordpress functions for dbDelta
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // dbDelta will create a new table if none exists or updae and existing one
        dbDelta($sql);

        // return value
        $return_value = true;

     } catch (Exception $e) {
         // Error
     }

     return $return_value;
}

// hint: runs funcions for plugin acrivation
function ssp_activate_plugin(){

    // create/update custom plugin tables
    ssp_create_plugin_tables();
}

// hint: ajax form handler for saving question responses
// expects: $_POST['survey_id'] and $_POST['response_id']
function ssp_ajax_save_response(){
    
    $result = array(
        'status'    => 0, 
        'message'   => 'Could not save response',
        'survey_complete'   => false
    );

    try {
        $survey_id = (isset($_POST['survey_id'])) ? (int)$_POST['survey_id'] : 0;
        $response_id = (isset($_POST['response_id'])) ? (int)$_POST['response_id'] : 0;

        if( !check_ajax_referer('ssp-save-survey-submission_' . $survey_id, false, false) ):

            $result['message'] .= ' Nounce invalid.';

        else:    
            $saved = ssp_save_response($survey_id, $response_id);

            if( $saved ):
                
                $survey = get_post($survey_id);
                if(isset($survey->post_type) && $survey->post_type == 'ssp_survey'):

                    $complete = true;

                    $html = ssp_get_question_html( $survey_id );

                    $result = array(
                        'status' => 1,  
                        'message' => 'Response saved!',
                        'survey_complete' => $complete,
                        'html' => $html,
                    );

                    if($complete): 
                        $result['message']='Survery complete!';
                    endif;
                else:
                    $result['message'] = ' Invalid Surver.';
                endif;

            endif;



        endif;



    } catch (Exception $e) {
        
    }
    ssp_return_json($result);
}