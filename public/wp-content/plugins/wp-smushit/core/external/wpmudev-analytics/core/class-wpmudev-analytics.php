<?php

use WPMUDEV_Analytics_Vendor\Mixpanel;

if ( class_exists( 'WPMUDEV_Analytics' ) ) {
	return;
}

/**
 * @method identify( int $user_id )
 * @method register( string $property, mixed $value )
 * @method registerAll( array $properties )
 */
class WPMUDEV_Analytics {
	const DATA_OPTION_ID = 'wpmudev_analytics_%s_data';
	const EXCEEDED_EVENT_NAME = 'exceeded_daily_limit';

	private $plugin_slug;
	private $plugin_name;
	private $event_limit;
	private $project_token;
	/**
	 * @var Mixpanel
	 */
	private $mixpanel;
	/**
	 * @var int
	 */
	private $time_window;
	/**
	 * @var array
	 */
	private $options;

	public function __construct( $plugin_slug, $plugin_name, $event_limit, $project_token, $options = array() ) {
		$this->plugin_slug   = $plugin_slug;
		$this->plugin_name   = $plugin_name;
		$this->project_token = $project_token;
		$this->options       = empty( $options ) ? array() : $options;

		$this->time_window = $this->get_constant_value( 'WPMUDEV_ANALYTICS_TIME_WINDOW_SECONDS', HOUR_IN_SECONDS * 24 );
		$this->event_limit = $this->get_constant_value( 'WPMUDEV_ANALYTICS_EVENT_LIMIT', $event_limit );
	}

	private function get_constant_value( $name, $default ) {
		return defined( $name ) && ! empty( constant( $name ) )
			? (int) constant( $name )
			: $default;
	}

	/**
	 * @return Mixpanel
	 */
	private function get_mixpanel() {
		if ( is_null( $this->mixpanel ) ) {
			$this->mixpanel = $this->prepare_mixpanel_instance();
		}

		return $this->mixpanel;
	}

	private function prepare_mixpanel_instance() {
		$options = array_merge(
			$this->options,
			array( 'error_callback' => array( $this, 'handle_error' ) )
		);

		return Mixpanel::getInstance( $this->project_token, $options );
	}

	public function handle_error( $code, $data ) {
		$plugin_name = $this->plugin_name;

		error_log( "$plugin_name: $code: $data" );
	}

	public function __call( $name, $arguments ) {
		if ( method_exists( $this->get_mixpanel(), $name ) ) {
			return call_user_func_array(
				array( $this->get_mixpanel(), $name ),
				$arguments
			);
		}

		return null;
	}

	public function track( $event, $properties = array() ) {
		$this->maybe_reset_data();

		$event_count = $this->get_event_count();
		$mixpanel    = $this->get_mixpanel();
		if ( $event_count < $this->event_limit ) {
			$mixpanel->track( $event, $properties );
		} else if ( $event_count === $this->event_limit ) {
			$mixpanel->track( self::EXCEEDED_EVENT_NAME, array(
				'Limit' => $this->event_limit,
			) );
		}

		$this->increment_count();
	}

	private function get_event_count() {
		$data = $this->get_data();
		return isset( $data['event_count'] )
			? (int) $data['event_count']
			: 0;
	}

	private function get_timestamp() {
		$data = $this->get_data();
		return isset( $data['timestamp'] )
			? (int) $data['timestamp']
			: 0;
	}

	private function maybe_reset_data() {
		$timestamp = $this->get_timestamp();
		$exceeded  = time() > $timestamp + $this->time_window;
		if ( $exceeded ) {
			$this->set_data( array(
				'timestamp'   => time(),
				'event_count' => 0,
			) );
		}
	}

	private function increment_count() {
		$this->set_data( array(
			'timestamp'   => $this->get_timestamp(),
			'event_count' => $this->get_event_count() + 1,
		) );
	}

	/**
	 * @return string
	 */
	private function get_option_key(): string {
		return sprintf( self::DATA_OPTION_ID, $this->plugin_slug );
	}

	private function get_data() {
		return get_option( $this->get_option_key() );
	}

	private function set_data( $data ) {
		if ( empty( $data ) ) {
			delete_option( $this->get_option_key() );
		} else {
			update_option( $this->get_option_key(), $data );
		}
	}
}
