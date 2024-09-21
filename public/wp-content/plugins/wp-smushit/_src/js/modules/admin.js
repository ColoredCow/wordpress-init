/* global ajaxurl */

import Smush from '../smush/smush';
import {GlobalStats} from "../common/globalStats";
import SmushProgress from "../common/progressbar";

const remove_element = function (el, timeout) {
	if (typeof timeout === 'undefined') {
		timeout = 100;
	}
	el.fadeTo(timeout, 0, function () {
		el.slideUp(timeout, function () {
			el.remove();
		});
	});
};

jQuery(function ($) {
	'use strict';

	/**
	 * Disable the action links *
	 *
	 * @param c_element
	 */
	const disable_links = function (c_element) {
		const parent = c_element.parent();
		//reduce parent opacity
		parent.css({ opacity: '0.5' });
		//Disable Links
		parent.find('a').prop('disabled', true);
	};

	/**
	 * Enable the Action Links *
	 *
	 * @param c_element
	 */
	const enable_links = function (c_element) {
		const parent = c_element.parent();

		//reduce parent opacity
		parent.css({ opacity: '1' });
		//Disable Links
		parent.find('a').prop('disabled', false);
	};

	/**
	 * Restore image request with a specified action for Media Library / NextGen Gallery
	 *
	 * @param {Object} e
	 * @param {string} currentButton
	 * @param {string} smushAction
	 * @param {string} action
	 */
	const process_smush_action = function (
		e,
		currentButton,
		smushAction,
		action
	) {
		e.preventDefault();

		// If disabled.
		if ( currentButton.attr( 'disabled' ) ) {
			return;
		}

		// Remove Error.
		$('.wp-smush-error').remove();

		// Hide stats.
		$('.smush-stats-wrapper').hide();

		let mode = 'grid';
		if ('smush_restore_image' === smushAction) {
			if ($(document).find('div.media-modal.wp-core-ui').length > 0) {
				mode = 'grid';
			} else {
				mode =
					window.location.search.indexOf('item') > -1
						? 'grid'
						: 'list';
			}
		}

		// Get the image ID and nonce.
		const params = {
			action: smushAction,
			attachment_id: currentButton.data('id'),
			mode,
			_nonce: currentButton.data('nonce'),
		};

		// Reduce the opacity of stats and disable the click.
		disable_links(currentButton);

		const oldLabel = currentButton.html();

		currentButton.html(
			'<span class="spinner wp-smush-progress">' +
				wp_smush_msgs[action] +
				'</span>'
		);

		// Restore the image.
		$.post(ajaxurl, params, function (r) {
			// Reset all functionality.
			enable_links(currentButton);

			if (r.success && 'undefined' !== typeof r.data) {
				// Replace in immediate parent for NextGEN.
				if (
					'undefined' !== typeof this.data &&
					this.data.indexOf('nextgen') > -1
				) {
					// Show the smush button, and remove stats and restore option.
					currentButton.parents().eq(1).html(r.data.stats);
				} else if ('restore' === action) {
					// Show the smush button, and remove stats and restore option.
					currentButton.parents().eq(1).html(r.data.stats);
				} else {
					const wrapper = currentButton.parents().eq(1);
					if ( wp_smush_msgs.failed_item_smushed && wrapper.hasClass('smush-failed-processing') ) {
						wrapper.html( '<p class="smush-status smush-success">' + wp_smush_msgs.failed_item_smushed  + '</p>' );
						setTimeout(function(){
							wrapper.html( r.data );
						}, 2000);
					} else {
						wrapper.html(r.data);
					}
				}

				if ('undefined' !== typeof r.data && 'restore' === action) {
					Smush.updateImageStats(r.data.new_size);
				}
			} else if (r.data && r.data.error_msg) {
				if (
					-1 === this.data.indexOf('nextgen')
				) {
					currentButton.closest( '.smushit' ).find('.smush-status').addClass('smush-warning').html(r.data.error_msg);
				} else {
					// Show error.
					currentButton.parent().append(r.data.error_msg);
				}

				// Reset label and disable button on error.
				currentButton.attr('disabled', true);
				currentButton.html( oldLabel );
			}
		});
	};

	/**
	 * Validates the Resize Width and Height against the Largest Thumbnail Width and Height
	 *
	 * @param wrapper_div jQuery object for the whole setting row wrapper div
	 * @param width_only Whether to validate only width
	 * @param height_only Validate only Height
	 * @return {boolean} All Good or not
	 */
	const validate_resize_settings = function (
		wrapper_div,
		width_only,
		height_only
	) {
		const resize_checkbox = wrapper_div.find('#resize');

		if (!height_only) {
			var width_input = wrapper_div.find('#wp-smush-resize_width');
			var width_error_note = wrapper_div.find(
				'.sui-notice-info.wp-smush-update-width'
			);
		}
		if (!width_only) {
			var height_input = wrapper_div.find('#wp-smush-resize_height');
			var height_error_note = wrapper_div.find(
				'.sui-notice-info.wp-smush-update-height'
			);
		}

		let width_error = false;
		let height_error = false;

		//If resize settings is not enabled, return true
		if (!resize_checkbox.is(':checked')) {
			return true;
		}

		//Check if we have localised width and height
		if (
			'undefined' === typeof wp_smushit_data.resize_sizes ||
			'undefined' === typeof wp_smushit_data.resize_sizes.width
		) {
			//Rely on server validation
			return true;
		}

		//Check for width
		if (
			!height_only &&
			'undefined' !== typeof width_input &&
			parseInt(wp_smushit_data.resize_sizes.width) >
				parseInt(width_input.val())
		) {
			width_input.parent().addClass('sui-form-field-error');
			width_error_note.show('slow');
			width_error = true;
		} else {
			//Remove error class
			width_input.parent().removeClass('sui-form-field-error');
			width_error_note.hide();
			if (height_input.hasClass('error')) {
				height_error_note.show('slow');
			}
		}

		//Check for height
		if (
			!width_only &&
			'undefined' !== typeof height_input &&
			parseInt(wp_smushit_data.resize_sizes.height) >
				parseInt(height_input.val())
		) {
			height_input.parent().addClass('sui-form-field-error');
			//If we are not showing the width error already
			if (!width_error) {
				height_error_note.show('slow');
			}
			height_error = true;
		} else {
			//Remove error class
			height_input.parent().removeClass('sui-form-field-error');
			height_error_note.hide();
			if (width_input.hasClass('error')) {
				width_error_note.show('slow');
			}
		}

		if (width_error || height_error) {
			return false;
		}
		return true;
	};

	/**
	 * Update the progress bar width if we have images that needs to be resmushed
	 *
	 * @param unsmushed_count
	 * @return {boolean}
	 */
	const update_progress_bar_resmush = function (unsmushed_count) {
		if ('undefined' === typeof unsmushed_count) {
			return false;
		}

		const smushed_count = wp_smushit_data.count_total - unsmushed_count;

		//Update the Progress Bar Width
		// get the progress bar
		const $progress_bar = jQuery(
			'.bulk-smush-wrapper .wp-smush-progress-inner'
		);
		if ($progress_bar.length < 1) {
			return;
		}

		const width = (smushed_count / wp_smushit_data.count_total) * 100;

		// increase progress
		$progress_bar.css('width', width + '%');
	};

	const runRecheck = function (process_settings) {
		const button = $('.wp-smush-scan');

		// Add a "loading" state to the button.
		button.addClass('sui-button-onload');

		// Check if type is set in data attributes.
		let scan_type = button.data('type');
		scan_type = 'undefined' === typeof scan_type ? 'media' : scan_type;

		// Remove the Skip resmush attribute from button.
		$('.wp-smush-all').removeAttr('data-smush');

		// Disable Bulk smush button and itself.
		$('.wp-smush-all').prop('disabled', true);

		// Hide Settings changed Notice.
		$('.wp-smush-settings-changed').hide();

		// Ajax params.
		const params = {
			action: 'scan_for_resmush',
			type: scan_type,
			get_ui: true,
			process_settings,
			wp_smush_options_nonce: jQuery('#wp_smush_options_nonce').val(),
		};

		// Send ajax request and get ids if any.
		$.get(ajaxurl, params, function (response) {
			if ( ! response?.success ) {
				WP_Smush.helpers.showNotice( response, {
					showdismiss: true,
					autoclose: false,
				} );
				return;
			}
			const stats = response.data;
			showRecheckImagesNotice( stats );
			GlobalStats.updateGlobalStatsFromSmushScriptData( stats );
			GlobalStats.renderStats();
			updateBulkSmushContentAfterReCheck( stats );
		}).always(function () {
			// Hide the progress bar.
			jQuery(
				'.bulk-smush-wrapper .wp-smush-bulk-progress-bar-wrapper'
			).addClass('sui-hidden');

			// Add check complete status to button.
			button
				.removeClass('sui-button-onload')
				.addClass('smush-button-check-success');

			const $defaultText = button.find('.wp-smush-default-text'),
				$completedText = button.find('.wp-smush-completed-text');

			$defaultText.addClass('sui-hidden-important');
			$completedText.removeClass('sui-hidden');

			// Remove success message from button.
			setTimeout(function () {
				button.removeClass('smush-button-check-success');

				$defaultText.removeClass('sui-hidden-important');
				$completedText.addClass('sui-hidden');
			}, 2000);

			$('.wp-smush-all').prop('disabled', false);
		});
	};

	const showRecheckImagesNotice = ( stats ) => {
		if ( ! stats.notice ) {
			return;
		}
		let type = 'success';
		if ( 'undefined' !== typeof stats.noticeType ) {
			type = stats.noticeType;
		}
		window.SUI.openNotice(
			'wp-smush-ajax-notice',
			'<p>' + stats.notice + '</p>',
			{ type, icon: 'check-tick' }
		);
	};

	const updateBulkSmushContentAfterReCheck = ( stats ) => {
		if ( SmushProgress.isEmptyObject ) {
			return;
		}

		SmushProgress.update( 0, stats.remaining_count );
		if ( stats.remaining_count < 1 ) {
			SmushProgress.hideBulkSmushDescription();
			SmushProgress.showBulkSmushAllDone();
		} else {
			SmushProgress.showBulkSmushDescription();
			SmushProgress.hideBulkSmushAllDone();
		}
	}

	const updateDisplayedContentAfterReCheck = function (count) {
		const $pendingImagesWrappers = jQuery(
			'.bulk-smush-wrapper .wp-smush-bulk-wrapper'
		);
		const $allDoneWrappers = jQuery(
			'.bulk-smush-wrapper .wp-smush-all-done'
		);

		if ($pendingImagesWrappers.length && $allDoneWrappers.length) {
			if (count === 0) {
				$pendingImagesWrappers.addClass('sui-hidden');
				$allDoneWrappers.find('p').html( wp_smush_msgs.all_smushed );
				$allDoneWrappers.find('.sui-notice-icon').removeClass('sui-icon-info').addClass('sui-icon-check-tick');
				$allDoneWrappers.removeClass('sui-notice-warning').addClass('sui-notice-success');
				$allDoneWrappers.removeClass('sui-hidden');
			} else {
				$pendingImagesWrappers.removeClass('sui-hidden');
				$allDoneWrappers.addClass('sui-hidden');

				// Update texts mentioning the amount of unsmushed imagesin the summary icon tooltip.
				const $unsmushedTooltip = jQuery(
					'.sui-summary-smush .sui-summary-details .sui-tooltip'
				);

				// The tooltip doesn't exist in the NextGen page.
				if ($unsmushedTooltip.length) {
					const textForm = 1 === count ? 'singular' : 'plural',
						tooltipText = $unsmushedTooltip
							.data(textForm)
							.replace('{count}', count);
					$unsmushedTooltip.attr('data-tooltip', tooltipText);
				}
			}
		}

		// Total count in the progress bar.
		jQuery('.wp-smush-total-count').text(count);
	};

	// Scroll the element to top of the page.
	const goToByScroll = function (selector) {
		// Scroll if element found.
		if ($(selector).length > 0) {
			$('html, body').animate(
				{
					scrollTop: $(selector).offset().top - 100,
				},
				'slow'
			);
		}
	};

	const update_cummulative_stats = function (stats) {
		//Update Directory Smush Stats
		if ('undefined' !== typeof stats.dir_smush) {
			const stats_human = $(
				'li.smush-dir-savings span.wp-smush-stats span.wp-smush-stats-human'
			);
			const stats_percent = $(
				'li.smush-dir-savings span.wp-smush-stats span.wp-smush-stats-percent'
			);

			// Do not replace if 0 savings.
			if (stats.dir_smush.bytes > 0) {
				$('.wp-smush-dir-link').addClass('sui-hidden');

				// Hide selector.
				$('li.smush-dir-savings .wp-smush-stats-label-message').hide();
				//Update Savings in bytes
				if (stats_human.length > 0) {
					stats_human.html(stats.dir_smush.human);
				}

				//Percentage section
				if (stats.dir_smush.percent > 0) {
					// Show size and percentage separator.
					$(
						'li.smush-dir-savings span.wp-smush-stats span.wp-smush-stats-sep'
					).removeClass('sui-hidden');
					//Update Optimisation percentage
					if (stats_percent.length > 0) {
						stats_percent.html(stats.dir_smush.percent + '%');
					}
				}
			} else {
				$('.wp-smush-dir-link').removeClass('sui-hidden');
			}
		}

		//Update Combined stats
		if (
			'undefined' !== typeof stats.combined_stats &&
			stats.combined_stats.length > 0
		) {
			const c_stats = stats.combined_stats;

			let smush_percent = (c_stats.smushed / c_stats.total_count) * 100;
			smush_percent = WP_Smush.helpers.precise_round(smush_percent, 1);

			//Smushed Percent
			if (smush_percent) {
				$('div.wp-smush-count-total span.wp-smush-images-percent').html(
					smush_percent
				);
			}
			//Update Total Attachment Count
			if (c_stats.total_count) {
				$(
					'span.wp-smush-count-total span.wp-smush-total-optimised'
				).html(c_stats.total_count);
			}
			//Update Savings and Percent
			if (c_stats.savings) {
				$('span.wp-smush-savings span.wp-smush-stats-human').html(
					c_stats.savings
				);
			}
			if (c_stats.percent) {
				$('span.wp-smush-savings span.wp-smush-stats-percent').html(
					c_stats.percent
				);
			}
		}
	};

	/**
	 * When 'All' is selected for the Image Sizes setting, select all available sizes.
	 *
	 * @since 3.2.1
	 */
	$('#all-image-sizes').on('change', function () {
		$('input[name^="wp-smush-image_sizes"]').prop('checked', true);
	});

	/**
	 * Handles the tabs navigation on mobile.
	 *
	 * @since 3.8.4
	 */
	$('.sui-mobile-nav').on('change', (e) => {
		window.location.assign($(e.currentTarget).val());
	});

	/**
	 * Handle re-check api status button click (Settings)
	 *
	 * @since 3.2.0.2
	 */
	$('#update-api-status').on('click', function (e) {
		e.preventDefault();

		//$(this).prop('disabled', true);
		$(this).addClass('sui-button-onload');

		$.post(ajaxurl, { action: 'recheck_api_status' }, function () {
			location.reload();
		});
	});

	/** Handle smush button click **/
	$('body').on(
		'click',
		'.wp-smush-send:not(.wp-smush-resmush)',
		function (e) {
			// prevent the default action
			e.preventDefault();
			new Smush($(this), false);
		}
	);

	/**
	 * Handle show in bulk smush button click.
	 */
	$( 'body' ).on( 'click', '.wp-smush-remove-skipped', function( e ) {
		e.preventDefault();

		const self = $( this );

		// Send ajax request to remove the image from the skip list.
		$.post( ajaxurl, {
			action: 'remove_from_skip_list',
			id: self.attr( 'data-id' ),
			_ajax_nonce: self.attr( 'data-nonce' ),
		} ).done( ( response ) => {
			if ( response.success && 'undefined' !== typeof response.data.html ) {
				self.parent().parent().html( response.data.html );
			}
		} );
	} );
	/** Restore: Media Library **/
	$('body').on('click', '.wp-smush-action.wp-smush-restore', function (e) {
		const current_button = $(this);
		process_smush_action(
			e,
			current_button,
			'smush_restore_image',
			'restore'
		);
	});

	/** Resmush: Media Library **/
	$('body').on('click', '.wp-smush-action.wp-smush-resmush', function (e) {
		process_smush_action(e, $(this), 'smush_resmush_image', 'smushing');
	});

	/** Restore: NextGen Gallery **/
	$('body').on(
		'click',
		'.wp-smush-action.wp-smush-nextgen-restore',
		function (e) {
			process_smush_action(
				e,
				$(this),
				'smush_restore_nextgen_image',
				'restore'
			);
		}
	);

	/** Resmush: NextGen Gallery **/
	$('body').on(
		'click',
		'.wp-smush-action.wp-smush-nextgen-resmush',
		function (e) {
			process_smush_action(
				e,
				$(this),
				'smush_resmush_nextgen_image',
				'smushing'
			);
		}
	);

	//Scan For resmushing images
	$('.wp-smush-scan').on('click', function (e) {
		e.preventDefault();
		if ( $(this).hasClass('wp-smush-background-scan') ) {
			return;
		}
		runRecheck(false);
	});

	//Remove Notice
	$('body').on('click', '.wp-smush-notice .icon-fi-close', function (e) {
		e.preventDefault();
		const $el = $(this).parent();
		remove_element($el);
	});

	// Enable super smush on clicking link from stats area.
	$('a.wp-smush-lossy-enable').on('click', function (e) {
		e.preventDefault();
		// Scroll down to settings area.
		goToByScroll('#column-lossy');
	});

	// Enable resize on clicking link from stats area.
	$('.wp-smush-resize-enable').on('click', function (e) {
		e.preventDefault();
		// Scroll down to settings area.
		goToByScroll('#column-resize');
	});

	// If settings string is found in url, enable and scroll.
	if ( window.location.hash ) {
		const setting_hash = window.location.hash.substring( 1 );
		let scrollTo = '';

		switch ( setting_hash ) {
			case 'enable-resize':
				scrollTo = '#column-resize';
				break;

			case 'backup-label':
				scrollTo = '#backup';
				break;

			case 'original-label':
				scrollTo = '#original';
				break;

			case 'enable-lossy':
				scrollTo = '#column-lossy';
				break;
		}

		if ( '' !== scrollTo ) {
			goToByScroll( scrollTo );
			document.getElementById( scrollTo.replace( '#', '' ) ).focus();
		}
	}

	//Trigger Bulk
	$('body').on('click', '.wp-smush-trigger-bulk', function (e) {
		e.preventDefault();

		//Induce Setting button save click
		if (
			'undefined' !== typeof e.target.dataset.type &&
			'nextgen' === e.target.dataset.type
		) {
			$('.wp-smush-nextgen-bulk').trigger('click');
		} else {
			$('.wp-smush-all').trigger('click');
		}

		$('span.sui-notice-dismiss').trigger('click');
	});

	//Trigger Bulk
	$('body').on('click', '#bulk-smush-top-notice-close', function (e) {
		e.preventDefault();
		$(this).parent().parent().slideUp('slow');
	});

	//Allow the checkboxes to be Keyboard Accessible
	$('.wp-smush-setting-row .toggle-checkbox').on('focus', function () {
		//If Space is pressed
		$(this).keypress(function (e) {
			if (e.keyCode == 32) {
				e.preventDefault();
				$(this).find('.toggle-checkbox').trigger('click');
			}
		});
	});

	// Re-Validate Resize Width And Height.
	$('body').on('blur', '.wp-smush-resize-input', function () {
		const self = $(this);

		const wrapper_div = self.parents().eq(4);

		// Initiate the check.
		validate_resize_settings(wrapper_div, false, false); // run the validation.
	});

	// Handle Resize Checkbox toggle, to show/hide width, height settings.
	$('body').on('click', '#resize', function () {
		const self = $(this);
		const settings_wrap = $('#smush-resize-settings-wrap');

		if (self.is(':checked')) {
			settings_wrap.show();
		} else {
			settings_wrap.hide();
		}
	});

	//Handle Re-check button functionality
	$('#wp-smush-revalidate-member').on('click', function (e) {
		e.preventDefault();
		//Ajax Params
		const params = {
			action: 'smush_show_warning',
			_ajax_nonce: window.wp_smush_msgs.nonce,
		};
		const link = $(this);
		const parent = link.parents().eq(1);
		parent.addClass('loading-notice');
		$.get(ajaxurl, params, function (r) {
			//remove the warning
			parent.removeClass('loading-notice').addClass('loaded-notice');
			if (0 == r) {
				parent.attr('data-message', wp_smush_msgs.membership_valid);
				remove_element(parent, 1000);
			} else {
				parent.attr('data-message', wp_smush_msgs.membership_invalid);
				setTimeout(function remove_loader() {
					parent.removeClass('loaded-notice');
				}, 1000);
			}
		});
	});

	if ($('li.smush-dir-savings').length > 0) {
		// Update Directory Smush, as soon as the page loads.
		const stats_param = {
			action: 'get_dir_smush_stats',
			_ajax_nonce: window.wp_smush_msgs.nonce,
		};
		$.get(ajaxurl, stats_param, function (r) {
			//Hide the spinner
			$('li.smush-dir-savings .sui-icon-loader').hide();

			//If there are no errors, and we have a message to display
			if (!r.success && 'undefined' !== typeof r.data.message) {
				$('div.wp-smush-scan-result div.content').prepend(
					r.data.message
				);
				return;
			}

			//If there is no value in r
			if (
				'undefined' === typeof r.data ||
				'undefined' === typeof r.data.dir_smush
			) {
				//Append the text
				$('li.smush-dir-savings span.wp-smush-stats').append(
					wp_smush_msgs.ajax_error
				);
				$('li.smush-dir-savings span.wp-smush-stats span').hide();
			} else {
				//Update the stats
				update_cummulative_stats(r.data);
			}
		});
	}

	// Display dialogs that show up with no user action.
	if ( $( '#smush-updated-dialog' ).length ) {
		// Displays the modal with the release's higlights if it exists.
		const modalId = 'smush-updated-dialog',
			focusAfterClosed = 'wpbody-content',
			focusWhenOpen = undefined,
			hasOverlayMask = false,
			isCloseOnEsc = false,
			isAnimated = true;

		window.SUI.openModal(
			modalId,
			focusAfterClosed,
			focusWhenOpen,
			hasOverlayMask,
			isCloseOnEsc,
			isAnimated
		);
	}

	/**
	 * Toggle backup notice based on "Optimize original images" setting.
	 * @since 3.9.1
	 */
	$( 'input#original' ).on( 'change', function() {
		$( '#backup-notice' ).toggleClass( 'sui-hidden', $( this ).is(':checked') );
	} );


	/**
	 * Bulk compression level notice.
	 */
	const handleCompressionLevelNotice = () => {
		const compressionLevelNotice = document.querySelector( '.wp-smush-compression-type' );
		if ( ! compressionLevelNotice ) {
			return;
		}
		const compressionNoticeContent = compressionLevelNotice.querySelector( '.wp-smush-compression-type_note p' );
		if ( ! compressionNoticeContent ) {
			return;
		}
		compressionLevelNotice.querySelector('.wp-smush-compression-type_slider').addEventListener('change', (e) => {
			if ( 'INPUT' !== e?.target?.nodeName ) {
				return;
			}
			const note = e.target.dataset?.note;
			if ( ! note ) {
				return;
			}

			compressionNoticeContent.innerHTML = note.trim();
		} );
	}
	handleCompressionLevelNotice();


	/**
	 * Close modal and redirect to the href link.
	 */
	$('.wp-smush-modal-link-close').on( 'click', function( e ) {
		e.preventDefault();
		SUI.closeModal();
		const href = $(this).attr('href');
		let openNewTab = '_blank' === $(this).attr('target');
		if ( href ) {
			if ( openNewTab ) {
				window.open( href, '_blank' );
			} else {
				window.location.href = href;
			}
		}
	});

	// Update Smush mode on lossy level change.
	const updateLossyLevelInSummaryBox = () => {
		const lossyLevelSummaryBox = document.querySelector('.wp-smush-current-compression-level');
		const currentLossyLevelTab = document.querySelector( '.wp-smush-lossy-level-tabs button.active' );
		if ( ! lossyLevelSummaryBox || ! currentLossyLevelTab ) {
			return;
		}
		// Update lossy label.
		lossyLevelSummaryBox.innerText = currentLossyLevelTab.innerText.trim();

		// Toggle Ultra notice/upsell link.
		const upsellLink = lossyLevelSummaryBox.nextElementSibling;
		if ( upsellLink ) {
			if ( currentLossyLevelTab.id.includes('ultra') ) {
				upsellLink.classList.add( 'sui-hidden' );
			} else {
				upsellLink.classList.remove( 'sui-hidden' );
			}
		}
	}

	document.addEventListener( 'onSavedSmushSettings', function( e ) {
		if ( ! e?.detail?.is_outdated_stats ) {
			return;
		}
		updateLossyLevelInSummaryBox();
	} );
});
