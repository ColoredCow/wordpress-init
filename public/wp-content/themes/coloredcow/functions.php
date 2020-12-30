<?php

add_action( 'wp_enqueue_scripts', 'cc_scripts' );
function cc_scripts() {
	wp_enqueue_script( 'cc-bootstrap', get_template_directory_uri() . '/dist/js/bootstrap.min.js', array( 'jquery' ), '1.0.0', true );
	wp_enqueue_script( 'main', get_template_directory_uri() . '/main.js', array( 'jquery', 'cc-bootstrap' ), filemtime( get_template_directory() . '/main.js' ), true );
	wp_localize_script(
		'main',
		'PARAMS',
		array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
		)
	);
}

add_action( 'wp_enqueue_scripts', 'cc_styles' );
function cc_styles() {
	wp_enqueue_style( 'style', get_template_directory_uri() . '/style.css', array(), filemtime( get_template_directory() . '/style.css' ) );
}

add_action( 'after_setup_theme', 'cc_theme_add_supports' );
function cc_theme_add_supports() {
	add_theme_support( 'custom-logo' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'align-full' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'menus' );
}

add_action( 'init', 'cc_theme_setup' );
function cc_theme_setup() {
	register_nav_menu( 'theme_header', 'Header Navigation' );
}
