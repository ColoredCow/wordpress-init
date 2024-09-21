<?php

namespace Smush\Core\Modules\Bulk;

use Smush\Core\Core;
use Smush\Core\Helper;
use Smush\Core\Media\Media_Item_Cache;
use Smush\Core\Media\Media_Item_Optimizer;
use Smush\Core\Modules\Background\Background_Process;
use Smush\Core\Modules\Smush;
use Smush\Core\Png2Jpg\Png2Jpg_Optimization;
use Smush\Core\Resize\Resize_Optimization;
use Smush\Core\Smush\Smush_Media_Item_Stats;
use Smush\Core\Smush\Smush_Optimization;
use WP_Smush;

class Bulk_Smush_Background_Process extends Background_Process {
	/**
	 * Retrival limit per 1000.
	 *
	 * @var int
	 */
	const REVIVAL_LIMIT_UNIT = 5;
	public function __construct( $identifier ) {
		parent::__construct( $identifier );

		$this->set_logger( Helper::logger() );
	}

	/**
	 * @param $task Smush_Background_Task
	 *
	 * @return boolean
	 */
	protected function task( $task ) {
		if ( ! is_a( $task, Smush_Background_Task::class ) || ! $task->is_valid() ) {
			Helper::logger()->error( 'An invalid background task was encountered.' );

			return false;
		}

		$attachment_id = $task->get_image_id();
		$media_item    = Media_Item_Cache::get_instance()->get( $attachment_id );
		$optimizer     = new Media_Item_Optimizer( $media_item );
		$optimized     = $optimizer->optimize();
		if ( ! $optimized ) {
			Helper::logger()->error( "Error encountered while smushing attachment ID $attachment_id:" . $optimizer->get_errors()->get_error_message() );

			return false;
		}

		$smush_optimization = $optimizer->get_optimization( Smush_Optimization::KEY );
		/**
		 * @var $smush_stats Smush_Media_Item_Stats
		 */
		$smush_stats          = $smush_optimization->get_stats();
		$resize_optimization  = $optimizer->get_optimization( Resize_Optimization::KEY );
		$png2jpg_optimization = $optimizer->get_optimization( Png2Jpg_Optimization::KEY );

		do_action(
			'image_smushed',
			$attachment_id,
			array(
				'count'              => $smush_optimization->get_optimized_sizes_count(),
				'size_before'        => $smush_stats->get_size_before(),
				'size_after'         => $smush_stats->get_size_after(),
				'savings_resize'     => $resize_optimization ? $resize_optimization->get_stats()->get_bytes() : 0,
				'savings_conversion' => $png2jpg_optimization ? $png2jpg_optimization->get_stats()->get_bytes() : 0,
				'is_lossy'           => $smush_stats->is_lossy(),
			)
		);

		return true;
	}

	/**
	 * Email when bulk smush complete.
	 */
	protected function complete() {
		parent::complete();
		// Send email.
		if ( $this->get_status()->get_total_items() ) {
			$mail = new Mail( 'wp_smush_background' );
			if ( $mail->reporting_email_enabled() ) {
				if ( $mail->send_email() ) {
					Helper::logger()->notice(
						sprintf(
							'Bulk Smush completed for %s, and sent a summary email to %s at %s.',
							get_site_url(),
							join( ',', $mail->get_mail_recipients() ),
							wp_date( 'd/m/y H:i:s' )
						)
					);
				} else {
					Helper::logger()->error(
						sprintf(
							'Bulk Smush completed for %s, but could not send a summary email to %s at %s.',
							get_site_url(),
							join( ',', $mail->get_mail_recipients() ),
							wp_date( 'd/m/y H:i:s' )
						)
					);
				}
			} else {
				Helper::logger()->info( sprintf( 'Bulk Smush completed for %s, and reporting email is disabled.', get_site_url() ) );
			}
		}

		do_action( 'wp_smush_bulk_smush_completed' );
	}

	protected function get_revival_limit() {
		$constant_value = $this->get_revival_limit_constant();
		if ( $constant_value ) {
			return $constant_value;
		}

		$revival_limit = $this->calculate_default_revival_limit();

		return apply_filters( $this->identifier . '_revival_limit', $revival_limit );
	}

	private function get_revival_limit_constant() {
		if ( ! defined( 'WP_SMUSH_BULK_REVIVAL_LIMIT' ) ) {
			return 0;
		}

		$constant_value = (int) WP_SMUSH_BULK_REVIVAL_LIMIT;

		return max( $constant_value, 0 );
	}

	private function calculate_default_revival_limit() {
		$total_items           = $this->get_status()->get_total_items();
		$default_revival_limit = (int) ceil( $total_items / 1000 ) * self::REVIVAL_LIMIT_UNIT;

		return max( $default_revival_limit, 5 );
	}

	protected function mark_as_dead() {
		do_action( 'wp_smush_bulk_smush_dead', $this );

		return parent::mark_as_dead();
	}
}
