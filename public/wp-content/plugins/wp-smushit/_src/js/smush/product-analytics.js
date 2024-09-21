import tracker from '../utils/tracker';

class ProductAnalytics {
	troubleshootClicked = false;
	resumeBulkSmushCount = 0;
	missedEventsKey = 'wp_smush_missed_events';

	init() {
		if ( ! tracker.allowToTrack() ) {
			return;
		}

		this.trackUltraLinks();
		this.trackUpsellLinks();

		this.registerTroubleshootClickEvent();

		// Scan Interrupted Event from Scan Modal.
		this.trackScanInterruptedEventOnStopScanningModal();
		this.trackScanInterruptedEventOnRetryScanModal();

		// Bulk Smush Interrupted Event from Bulk Smush Modal.
		this.trackBulkSmushInterruptedEventOnStopBulkSmushModal();
		this.trackBulkSmushInterruptedEventOnRetryBulkSmushModal();
		this.registerBulkSmushResumeClickEvent();

		// Bulk Smush Interrupted Event when exit ajax bulk smush.
		this.trackBulkSmushInterruptedEventWhenExitingAjaxBulkSmush();

		// Interrupted Event from Inline Notice.
		this.trackInterruptedEventFromInlineNotice();

		// Interrupted Event from Loopback Error Modal.
		this.trackInterruptedEventFromLoopbackErrorModal();

		this.maybeTrackMissedEventsOnLoad();
	}

	trackUltraLinks() {
		const ultraUpsellLinks = document.querySelectorAll( '.wp-smush-upsell-ultra-compression' );
		if ( ! ultraUpsellLinks ) {
			return;
		}
		const getLocation = ( ultraLink ) => {
			const locations = {
				settings: 'bulksmush_settings',
				dashboard: 'dash_summary',
				bulk: 'bulksmush_summary',
				directory: 'directory_summary',
				'lazy-load': 'lazy_summary',
				cdn: 'cdn_summary',
				webp: 'webp_summary',
			};
			const locationId = ultraLink.classList.contains( 'wp-smush-ultra-compression-link' ) ? 'settings' : this.getCurrentPageSlug();
			return locations[ locationId ] || 'bulksmush_settings';
		};

		ultraUpsellLinks.forEach( ( ultraLink ) => {
			const eventName = 'ultra_upsell_modal';
			ultraLink.addEventListener( 'click', ( e ) => {
				tracker.track( eventName, {
					Location: getLocation( e.target ),
					'Modal Action': 'direct_cta',
				} );
			} );
		} );
	}

	trackUpsellLinks() {
		const upsellLinks = document.querySelectorAll( '[href*="utm_source=smush"]' );
		if ( ! upsellLinks ) {
			return;
		}
		upsellLinks.forEach( ( upsellLink ) => {
			upsellLink.addEventListener( 'click', ( e ) => {
				const params = new URL( e.target.href ).searchParams;
				if ( ! params ) {
					return;
				}

				const campaign = params.get( 'utm_campaign' );
				const upsellLocations = {
					// CDN.
					summary_cdn: 'dash_summary',
					'smush-dashboard-cdn-upsell': 'dash_widget',
					smush_bulksmush_cdn: 'bulk_smush_progress',
					smush_cdn_upgrade_button: 'cdn_page',
					smush_bulksmush_library_gif_cdn: 'media_library',
					smush_bulk_smush_complete_global: 'bulk_smush_complete',

					// Local WebP.
					summary_local_webp: 'dash_summary',
					'smush-dashboard-local-webp-upsell': 'dash_widget',
					// smush_webp_upgrade_button: 'webp_page',// Handled inside React WebP - free-content.jsx
				};

				if ( ! ( campaign in upsellLocations ) ) {
					return;
				}

				const Location = upsellLocations[ campaign ];
				const matches = campaign.match( /(cdn|webp)/i );
				const upsellModule = matches && matches[ 0 ];

				const eventName = 'webp' === upsellModule ? 'local_webp_upsell' : 'cdn_upsell';
				tracker.track( eventName, { Location } );
			} );
		} );
	}

	trackScanInterruptedEventOnStopScanningModal() {
		const stopScanningModal = document.getElementById( 'smush-stop-scanning-dialog' );
		if ( ! stopScanningModal ) {
			return;
		}

		const closeButtons = stopScanningModal.querySelectorAll( '[data-modal-close]' );
		closeButtons.forEach( ( closeButton ) => {
			closeButton.addEventListener( 'click', ( e ) => {
				const action = e.target.dataset?.action || 'Close';
				this.trackScanInterruptedEvent( {
					Trigger: 'cancel_in_progress',
					'Modal Action': action,
				} );
			} );
		} );
	}

	trackBulkSmushInterruptedEventOnStopBulkSmushModal() {
		const stopBulkSmushModal = document.getElementById( 'smush-stop-bulk-smush-modal' );
		if ( ! stopBulkSmushModal ) {
			return;
		}
		const closeButtons = stopBulkSmushModal.querySelectorAll( '[data-modal-close]' );
		closeButtons.forEach( ( closeButton ) => {
			closeButton.addEventListener( 'click', ( e ) => {
				const action = e.target.dataset?.action || 'Close';
				this.trackBulkSmushInterruptedEvent( {
					Trigger: 'cancel_in_progress',
					'Modal Action': action,
				} );
			} );
		} );
	}

	trackScanInterruptedEventOnRetryScanModal() {
		const retryScanModal = document.getElementById( 'smush-retry-scan-notice' );
		if ( ! retryScanModal ) {
			return;
		}
		const retryButton = retryScanModal.querySelector( '.smush-retry-scan-notice-button' );
		if ( retryButton ) {
			retryButton.addEventListener( 'click', ( e ) => {
				const recheckImagesBtn = document.querySelector( '.wp-smush-scan' );
				if ( recheckImagesBtn ) {
					this.trackScanInterruptedEvent( {
						Trigger: 'failed_modal',
						'Modal Action': 'Retry',
					} );
					return;
				}
				e.preventDefault();

				const event = 'Scan Interrupted';
				const properties = this.getScanInterruptedEventProperties( {
					Trigger: 'failed_modal',
					'Modal Action': 'Retry',
				} );

				tracker.track( event, properties ).catch( () => {
					this.cacheMissedEvent( {
						event,
						properties,
					} );
				} ).finally( () => {
					window.location.href = e.target.href;
				} );
			} );
		}
		const closeButtons = retryScanModal.querySelectorAll( '[data-modal-close]' );
		closeButtons.forEach( ( closeButton ) => {
			closeButton.addEventListener( 'click', ( e ) => {
				const action = e.target.dataset?.action || 'Close';
				this.trackScanInterruptedEvent( {
					Trigger: 'failed_modal',
					'Modal Action': action,
				} );
			} );
		} );
	}

	trackBulkSmushInterruptedEventOnRetryBulkSmushModal() {
		const retryBulkModal = document.getElementById( 'smush-retry-bulk-smush-notice' );
		if ( ! retryBulkModal ) {
			return;
		}

		const retryButton = retryBulkModal.querySelector( '.smush-retry-bulk-smush-notice-button' );
		if ( retryButton ) {
			retryButton.addEventListener( 'click', () => {
				this.trackBulkSmushInterruptedEvent( {
					Trigger: 'failed_modal',
					'Modal Action': 'Retry',
				} );
			} );
		}

		const closeButtons = retryBulkModal.querySelectorAll( '[data-modal-close]' );
		closeButtons.forEach( ( closeButton ) => {
			closeButton.addEventListener( 'click', ( e ) => {
				const action = e.target.dataset?.action || 'Close';
				this.trackBulkSmushInterruptedEvent( {
					Trigger: 'failed_modal',
					'Modal Action': action,
				} );
			} );
		} );
	}

	trackScanInterruptedEvent( properties ) {
		return tracker.track( 'Scan Interrupted', this.getScanInterruptedEventProperties( properties ) );
	}

	getScanInterruptedEventProperties( properties ) {
		return Object.assign( {
			Troubleshoot: this.troubleshootClicked ? 'Yes' : 'No',
		}, properties );
	}

	trackBulkSmushInterruptedEventWhenExitingAjaxBulkSmush() {
		if ( this.canUseBackgroundOptimization() ) {
			return;
		}

		const progressBar = document.querySelector( '.wp-smush-bulk-progress-bar-wrapper' );
		if ( ! progressBar ) {
			return;
		}

		window.addEventListener( 'beforeunload', () => {
			const ajaxBulkSmushObject = window.WP_Smush?.bulk?.bulkSmush;
			const isBulkSmushInProgressing = ajaxBulkSmushObject && ajaxBulkSmushObject.ids.length > 0 && ! progressBar.classList.contains( 'sui-hidden' );
			if ( ! isBulkSmushInProgressing ) {
				return;
			}

			const isFreeExceeded = progressBar.classList.contains( 'wp-smush-exceed-limit' );

			const event = 'Bulk Smush Interrupted';
			const properties = this.getBulkSmushInterruptedEventProperties(
				{
					Trigger: isFreeExceeded ? 'exit_50_limit' : 'exit_in_progress',
					'Modal Action': 'Exit',
					'Retry Attempts': this.resumeBulkSmushCount,
				}
			);

			tracker.track( event, properties ).catch( () => {
				this.cacheMissedEvent( {
					event,
					properties,
				} );
			} );
		} );
	}

	cacheMissedEvent( eventData ) {
		if ( window.localStorage ) {
			// As now we only use it for one event, so let's keep it as a simple array.
			window.localStorage.setItem( this.missedEventsKey, JSON.stringify( [ eventData ] ) );
		}
	}

	getMissedEvents() {
		if ( ! window.localStorage ) {
			return [];
		}

		const properties = window.localStorage.getItem( this.missedEventsKey );
		if ( ! properties ) {
			return [];
		}

		return JSON.parse( properties );
	}

	clearMissedEvents() {
		if ( window.localStorage ) {
			window.localStorage.removeItem( this.missedEventsKey );
		}
	}

	canUseBackgroundOptimization() {
		return 'undefined' !== typeof window.wp_smushit_data?.bo_stats;
	}

	trackBulkSmushInterruptedEvent( properties ) {
		return tracker.track( 'Bulk Smush Interrupted', this.getBulkSmushInterruptedEventProperties( properties ) );
	}

	getBulkSmushInterruptedEventProperties( properties ) {
		return Object.assign(
			{
				Troubleshoot: this.troubleshootClicked ? 'Yes' : 'No',
			},
			this.getBulkSmushProcessStats(),
			properties
		);
	}

	getBulkSmushProcessStats() {
		if ( this.canUseBackgroundOptimization() ) {
			// Handled it via PHP.
			return {};
		}
		const ajaxBulkSmushObject = window.WP_Smush?.bulk?.bulkSmush;
		const totalEnqueuedImages = ajaxBulkSmushObject?.total || 0;
		const processedImages = ajaxBulkSmushObject?.smushed + ajaxBulkSmushObject?.errors.length;
		const completionPercentage = totalEnqueuedImages > 0 ? Math.ceil( processedImages * 100 / totalEnqueuedImages ) : 0;

		return {
			'Retry Attempts': this.resumeBulkSmushCount,
			'Total Enqueued Images': totalEnqueuedImages,
			'Completion Percentage': completionPercentage,
		};
	}

	trackInterruptedEventFromInlineNotice() {
		this.trackInterruptedEventFromInlineNoticeOnDashboard();
		this.trackBulkSmushInterruptedEventFromInlineNoticeOnBulkSmush();
		this.trackScanInterruptedEventFromInlineNoticeOnBulkSmush();
	}

	trackInterruptedEventFromInlineNoticeOnDashboard() {
		const dashboardBulkElement = document.getElementById( 'smush-box-dashboard-bulk' );
		if ( ! dashboardBulkElement ) {
			return;
		}
		this.trackBulkSmushInterruptedEventFromInlineNoticeOnDashboard( dashboardBulkElement );
		this.trackScanInterruptedEventFromInlineNoticeOnDashboard( dashboardBulkElement );
	}

	trackBulkSmushInterruptedEventFromInlineNoticeOnDashboard( dashboardBulkElement ) {
		const triggerBulkSmushButton = dashboardBulkElement.querySelector( '.wp-smush-retry-bulk-smush-link' );
		if ( ! triggerBulkSmushButton ) {
			return;
		}
		triggerBulkSmushButton.addEventListener( 'click', ( e ) => {
			e.preventDefault();

			const event = 'Bulk Smush Interrupted';
			const properties = this.getBulkSmushInterruptedEventProperties(
				{
					Trigger: 'failed_notice',
					'Modal Action': 'Retry',
				}
			);

			tracker.track( event, properties ).catch( () => {
				this.cacheMissedEvent( {
					event,
					properties,
				} );
			} ).finally( () => {
				window.location.href = e.target.href;
			} );
		} );
	}

	trackBulkSmushInterruptedInlineNoticeEvent() {
		return this.trackBulkSmushInterruptedEvent( {
			Trigger: 'failed_notice',
			'Modal Action': 'Retry',
		} );
	}

	trackBulkSmushInterruptedEventFromInlineNoticeOnBulkSmush() {
		const triggerBulkSmushButton = document.querySelector( '.wp-smush-inline-retry-bulk-smush-notice .wp-smush-trigger-bulk-smush' );
		if ( ! triggerBulkSmushButton ) {
			return;
		}

		triggerBulkSmushButton.addEventListener( 'click', () => {
			this.trackBulkSmushInterruptedInlineNoticeEvent();
		} );
	}

	trackScanInterruptedEventFromInlineNoticeOnDashboard( dashboardBulkElement ) {
		const triggerScanButton = dashboardBulkElement.querySelector( '.wp-smush-retry-scan-link' );
		if ( ! triggerScanButton ) {
			return;
		}

		triggerScanButton.addEventListener( 'click', ( e ) => {
			e.preventDefault();

			const event = 'Scan Interrupted';
			const properties = this.getScanInterruptedEventProperties( {
				Trigger: 'failed_notice',
				'Modal Action': 'Retry',
			} );

			tracker.track( event, properties ).catch( () => {
				this.cacheMissedEvent( {
					event,
					properties,
				} );
			} ).finally( () => {
				window.location.href = e.target.href;
			} );
		} );
	}

	trackScanInterruptedEventFromInlineNoticeOnBulkSmush() {
		const recheckImagesNotice = document.querySelector( '.wp-smush-recheck-images-notice-box' );
		if ( ! recheckImagesNotice ) {
			return;
		}
		const triggerBackgroundScanImagesLink = recheckImagesNotice.querySelector( '.wp-smush-trigger-background-scan' );
		if ( triggerBackgroundScanImagesLink ) {
			triggerBackgroundScanImagesLink.addEventListener( 'click', () => {
				// We are using the same frame for failed scan notice and generic required scan notice,
				// so we need to verify the failed notice before tracking the event.
				const containTroubleshootingLink = triggerBackgroundScanImagesLink?.previousElementSibling?.querySelector( 'a' );
				if ( ! containTroubleshootingLink ) {
					return;
				}
				this.trackScanInterruptedEvent( {
					Trigger: 'failed_notice',
					'Modal Action': 'Retry',
				} );
			} );
		}
	}

	trackInterruptedEventFromLoopbackErrorModal() {
		const loopbackErrorModal = document.getElementById( 'smush-loopback-error-dialog' );
		if ( ! loopbackErrorModal ) {
			return;
		}

		const loopbackErrorDocsLink = loopbackErrorModal.querySelector( 'a[href*="#loopback-request-issue"]' );
		let isTroubleshootClicked = false;
		if ( loopbackErrorDocsLink ) {
			loopbackErrorDocsLink.addEventListener( 'click', () => {
				isTroubleshootClicked = true;
			}, { once: true } );
		}

		const trackLoopbackErrorEvent = ( action, processType ) => {
			const properties = {
				Trigger: 'loopback_error',
				'Modal Action': action,
				Troubleshoot: isTroubleshootClicked ? 'Yes' : 'No',
			};

			if ( 'scan' === processType ) {
				this.trackScanInterruptedEvent( properties );
			} else {
				this.trackBulkSmushInterruptedEvent( properties );
			}
		};

		const closeButtons = loopbackErrorModal.querySelectorAll( '[data-modal-close]' );
		closeButtons.forEach( ( closeButton ) => {
			closeButton.addEventListener( 'click', ( e ) => {
				const action = e.target.dataset?.action || 'Close';
				const processType = loopbackErrorModal.dataset?.processType || 'scan';
				trackLoopbackErrorEvent( action, processType );
			} );
		} );
	}

	registerTroubleshootClickEvent() {
		const troubleshootLinks = document.querySelectorAll( 'a[href*="#troubleshooting-guide"]' );
		if ( ! troubleshootLinks ) {
			return;
		}

		troubleshootLinks.forEach( ( troubleshootLink ) => {
			troubleshootLink.addEventListener( 'click', () => {
				this.troubleshootClicked = true;
			}, { once: true } );
		} );
	}

	maybeTrackMissedEventsOnLoad() {
		window.addEventListener( 'load', () => {
			const missedEvents = this.getMissedEvents();
			if ( 0 === missedEvents.length ) {
				return;
			}

			this.clearMissedEvents();

			missedEvents.forEach( ( missedEvent ) => {
				tracker.track( missedEvent.event, missedEvent.properties );
			} );
		} );
	}

	registerBulkSmushResumeClickEvent() {
		const resumeBulkSmushButton = document.querySelector( '.wp-smush-resume-bulk-smush' );
		if ( ! resumeBulkSmushButton ) {
			return;
		}

		resumeBulkSmushButton.addEventListener( 'click', () => {
			this.resumeBulkSmushCount += 1;
		} );
	}

	getCurrentPageSlug() {
		const searchParams = new URLSearchParams( document.location.search );
		const pageSlug = searchParams.get( 'page' );
		return 'smush' === pageSlug ? 'dashboard' : pageSlug.replace( 'smush-', '' );
	}
}

( new ProductAnalytics() ).init();
