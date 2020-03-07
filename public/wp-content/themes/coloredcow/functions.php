<?php

if ( ! function_exists( 'cc_scripts' ) ) {
	function cc_scripts() {
		wp_enqueue_script( 'cc-bootstrap', get_template_directory_uri() . '/dist/js/bootstrap.min.js', array( 'jquery' ), '1.0.0', true );
		wp_enqueue_script( 'main', get_template_directory_uri() . '/main.js', array( 'jquery', 'cc-bootstrap' ), filemtime( get_template_directory() . '/main.js' ) , true );
		wp_localize_script( 'main', 'PARAMS', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
		));
	}
	add_action( 'wp_enqueue_scripts', 'cc_scripts' );
}

if ( ! function_exists( 'cc_styles' ) ) {
	function cc_styles() {
		wp_enqueue_style( 'style', get_template_directory_uri() . '/style.css', array(), filemtime( get_template_directory() . '/style.css' ) );
	}
	add_action( 'wp_enqueue_scripts', 'cc_styles' );
}

add_filter( 'show_admin_bar', '__return_false' );
