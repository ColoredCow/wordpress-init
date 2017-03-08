<?php 

if ( ! function_exists( 'cc_scripts' ) ) {
    function cc_scripts() {
        wp_enqueue_script('cc-jquery', get_template_directory_uri().'/dist/lib/js/jquery-1.11.3.min.js');
        wp_enqueue_script('cc-bootstrap', get_template_directory_uri().'/dist/lib/js/bootstrap.min.js');
        wp_enqueue_script('main', get_template_directory_uri().'/main.js');

    }
    add_action('wp_enqueue_scripts','cc_scripts');
}

if ( ! function_exists( 'cc_styles' ) ) {
    function cc_styles() {  
        wp_enqueue_style('cc-bootstrap', get_template_directory_uri().'/dist/lib/css/bootstrap.min.css');
        wp_enqueue_style('style', get_template_directory_uri().'/style.css');

    }
    add_action('wp_enqueue_scripts','cc_styles');
}

//add filter to remove margin above html
add_filter('show_admin_bar','__return_false');

?>
