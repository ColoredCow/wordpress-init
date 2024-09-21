<?php
/**
 * WPMUDEV Notices - Notices module for WPMUDEV free plugins.
 *
 * Used by wordpress.org hosted plugins to show email optins, rating notice
 * and giveaway notices.
 *
 * @since   2.0
 * @author  Incsub (Philipp Stracker, Joel James)
 * @package WPMUDEV\Notices
 */

if ( ! class_exists( 'WPMUDEV\Notices\Handler' ) ) {
	// Base file.
	if ( ! defined( 'WPMUDEV_NOTICES_FILE' ) ) {
		define( 'WPMUDEV_NOTICES_FILE', __FILE__ );
	}

	// Include main module.
	require_once 'classes/class-handler.php';
	// Include notices.
	require_once 'classes/notices/class-notice.php';
	require_once 'classes/notices/class-email.php';
	require_once 'classes/notices/class-rating.php';
	require_once 'classes/notices/class-giveaway.php';

	// Initialize notices.
	WPMUDEV\Notices\Handler::instance();
}
