/* global WP_Smush */
/* global ajaxurl */
import tracker from "../utils/tracker";

/**
 * Modals JavaScript code.
 */
( function() {
	'use strict';

	/**
	 * Onboarding modal.
	 *
	 * @since 3.1
	 */
	WP_Smush.onboarding = {
		membership: 'free', // Assume free by default.
		onboardingModal: document.getElementById( 'smush-onboarding-dialog' ),
		first_slide: 'usage',
		settings: {
			first: true,
			last: false,
			slide: 'usage',
			value: false,
		},
		selection: {
			usage: false,
			auto: true,
			lossy: true,
			strip_exif: true,
			original: false,
			lazy_load: true,
		},
		contentContainer: document.getElementById( 'smush-onboarding-content' ),
		onboardingSlides: [
			'usage',
			'auto',
			'lossy',
			'strip_exif',
			'original',
			'lazy_load',
		],
		touchX: null,
		touchY: null,
		recheckImagesLink: '',
		/**
		 * Init module.
		 */
		init() {
			if ( ! this.onboardingModal ) {
				return;
			}

			const dialog = document.getElementById( 'smush-onboarding' );

			this.membership = dialog.dataset.type;
			this.recheckImagesLink = dialog.dataset.ctaUrl;

			if ( 'pro' !== this.membership ) {
				this.onboardingSlides = [
					'usage',
					'auto',
					'lossy',
					'strip_exif',
					'lazy_load',
				];
			}

			if ( 'false' === dialog.dataset.tracking ) {
				this.onboardingSlides.pop();
			}

			this.renderTemplate();

			// Skip setup.
			const skipButton = this.onboardingModal.querySelector(
				'.smush-onboarding-skip-link'
			);
			if ( skipButton ) {
				skipButton.addEventListener( 'click', this.skipSetup.bind( this ) );
			}

			// Show the modal.
			window.SUI.openModal(
				'smush-onboarding-dialog',
				'wpcontent',
				undefined,
				false
			);
		},

		/**
		 * Get swipe coordinates.
		 *
		 * @param {Object} e
		 */
		handleTouchStart( e ) {
			const firstTouch = e.touches[ 0 ];
			this.touchX = firstTouch.clientX;
			this.touchY = firstTouch.clientY;
		},

		/**
		 * Process swipe left/right.
		 *
		 * @param {Object} e
		 */
		handleTouchMove( e ) {
			if ( ! this.touchX || ! this.touchY ) {
				return;
			}

			const xUp = e.touches[ 0 ].clientX,
				yUp = e.touches[ 0 ].clientY,
				xDiff = this.touchX - xUp,
				yDiff = this.touchY - yUp;

			if ( Math.abs( xDiff ) > Math.abs( yDiff ) ) {
				if ( xDiff > 0 ) {
					if ( false === WP_Smush.onboarding.settings.last ) {
						WP_Smush.onboarding.next( null, 'next' );
					}
				} else if ( false === WP_Smush.onboarding.settings.first ) {
					WP_Smush.onboarding.next( null, 'prev' );
				}
			}

			this.touchX = null;
			this.touchY = null;
		},

		/**
		 * Update the template, register new listeners.
		 *
		 * @param {string} directionClass Accepts: fadeInRight, fadeInLeft, none.
		 */
		renderTemplate( directionClass = 'none' ) {
			// Grab the selected value.
			const input = this.onboardingModal.querySelector(
				'input[type="checkbox"]'
			);
			if ( input ) {
				this.selection[ input.id ] = input.checked;
			}

			const template = WP_Smush.onboarding.template( 'smush-onboarding' );
			const content = template( this.settings );

			if ( content ) {
				this.contentContainer.innerHTML = content;

				if ( 'none' === directionClass ) {
					this.contentContainer.classList.add( 'loaded' );
				} else {
					this.contentContainer.classList.remove( 'loaded' );
					this.contentContainer.classList.add( directionClass );
					setTimeout( () => {
						this.contentContainer.classList.add( 'loaded' );
						this.contentContainer.classList.remove(
							directionClass
						);
					}, 600 );
				}
			}

			this.onboardingModal.addEventListener(
				'touchstart',
				this.handleTouchStart,
				false
			);
			this.onboardingModal.addEventListener(
				'touchmove',
				this.handleTouchMove,
				false
			);

			this.bindSubmit();
		},

		/**
		 * Catch "Finish setup wizard" button click.
		 */
		bindSubmit() {
			const submitButton = this.onboardingModal.querySelector(
				'button[type="submit"]'
			);
			const self = this;

			if ( submitButton ) {
				submitButton.addEventListener( 'click', function( e ) {
					e.preventDefault();

					// Because we are not rendering the template, we need to update the last element value.
					const input = self.onboardingModal.querySelector(
						'input[type="checkbox"]'
					);
					if ( input ) {
						self.selection[ input.id ] = input.checked;
					}

					const _nonce = document.getElementById(
						'smush_quick_setup_nonce'
					);

					const xhr = new XMLHttpRequest();
					xhr.open( 'POST', ajaxurl + '?action=smush_setup', true );
					xhr.setRequestHeader(
						'Content-type',
						'application/x-www-form-urlencoded'
					);
					xhr.onload = () => {
						if ( 200 === xhr.status ) {
							self.onFinishingSetup();
						} else {
							window.console.log(
								'Request failed.  Returned status of ' +
									xhr.status
							);
						}
					};
					xhr.send(
						'smush_settings=' +
							JSON.stringify( self.selection ) +
							'&_ajax_nonce=' +
							_nonce.value
					);
				} );
			}
		},

		onFinishingSetup() {
			this.onFinish();
			this.startRecheckImages();
		},

		onFinish() {
			window.SUI.closeModal();
		},

		startRecheckImages() {
			if ( ! this.recheckImagesLink ) {
				return;
			}
			window.location.href = this.recheckImagesLink;
		},

		/**
		 * Handle navigation.
		 *
		 * @param {Object}      e
		 * @param {null|string} whereTo
		 */
		next( e, whereTo = null ) {
			const index = this.onboardingSlides.indexOf( this.settings.slide );
			let newIndex = 0;

			if ( ! whereTo ) {
				newIndex =
					null !== e && e.classList.contains( 'next' )
						? index + 1
						: index - 1;
			} else {
				newIndex = 'next' === whereTo ? index + 1 : index - 1;
			}

			const directionClass =
				null !== e && e.classList.contains( 'next' )
					? 'fadeInRight'
					: 'fadeInLeft';

			this.settings = {
				first: 0 === newIndex,
				last: newIndex + 1 === this.onboardingSlides.length, // length !== index
				slide: this.onboardingSlides[ newIndex ],
				value: this.selection[ this.onboardingSlides[ newIndex ] ],
			};

			this.renderTemplate( directionClass );
		},

		/**
		 * Handle circle navigation.
		 *
		 * @param {string} target
		 */
		goTo( target ) {
			const newIndex = this.onboardingSlides.indexOf( target );

			this.settings = {
				first: 0 === newIndex,
				last: newIndex + 1 === this.onboardingSlides.length, // length !== index
				slide: target,
				value: this.selection[ target ],
			};

			this.renderTemplate();
		},

		/**
		 * Skip onboarding experience.
		 */
		skipSetup() {
			const _nonce = document.getElementById( 'smush_quick_setup_nonce' );

			const xhr = new XMLHttpRequest();
			xhr.open(
				'POST',
				ajaxurl + '?action=skip_smush_setup&_ajax_nonce=' + _nonce.value
			);
			xhr.onload = () => {
				if ( 200 === xhr.status ) {
					this.onSkipSetup();
				} else {
					window.console.log(
						'Request failed.  Returned status of ' + xhr.status
					);
				}
			};
			xhr.send();
		},

		onSkipSetup() {
			this.onFinish();
		},

		/**
		 * Hide new features modal.
		 * @since 3.7.0
		 * @since 3.12.2 Add a new parameter redirectUrl
		 */
		hideUpgradeModal: ( e, button ) => {
			e.preventDefault();
			button.classList.add( 'wp-smush-link-in-progress' );
			const redirectUrl = button?.href;
			const xhr = new XMLHttpRequest();
			xhr.open( 'POST', ajaxurl + '?action=hide_new_features&_ajax_nonce=' + window.wp_smush_msgs.nonce );
			xhr.onload = () => {
				window.SUI.closeModal();
				button.classList.remove( 'wp-smush-link-in-progress' );

				const actionName = redirectUrl ? 'cta_clicked' : 'closed';
				tracker.track( 'update_modal_displayed', {
					Action: actionName,
				} );

				if ( 200 === xhr.status ) {
					if ( redirectUrl ) {
						window.location.href = redirectUrl;
					}
				} else {
					window.console.log(
						'Request failed.  Returned status of ' + xhr.status
					);
				}
			};
			xhr.send();
		},
	};

	/**
	 * Template function (underscores based).
	 *
	 * @type {Function}
	 */
	WP_Smush.onboarding.template = _.memoize( ( id ) => {
		let compiled;
		const options = {
			evaluate: /<#([\s\S]+?)#>/g,
			interpolate: /{{{([\s\S]+?)}}}/g,
			escape: /{{([^}]+?)}}(?!})/g,
			variable: 'data',
		};

		return ( data ) => {
			_.templateSettings = options;
			compiled =
				compiled ||
				_.template( document.getElementById( id ).innerHTML );
			data.first_slide = WP_Smush.onboarding.first_slide;
			return compiled( data );
		};
	} );

	window.addEventListener( 'load', () => WP_Smush.onboarding.init() );
}() );
