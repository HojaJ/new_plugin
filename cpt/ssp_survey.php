<?php

add_action('init', 'ssp_register_ssp_survey');
function ssp_register_ssp_survey()
{
    $labels = array(
        'name' => 'Surveys', 
        'singular_name' => 'Survey', 
    );
    $args = array(
        'labels'             => $labels,
        'description'         => '',
		'public'              => false,
        'show_ui'             => true,
        'has_archive'         => false,
        'show_in_menu'        => false,
		'exclude_from_search' => true,
		'capability_type'     => 'post',
		'map_meta_cap'        => true,
		'hierarchical'        => false,
		'rewrite'             => array( 'slug'=>'ssp_survey', 'with_front'=>false ),
		'query_var'           => true,
		'supports'            => array( 'title', 'editor' ),
    );
    register_post_type('ssp_survey', $args);
}