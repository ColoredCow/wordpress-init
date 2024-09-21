/**
 * External dependencies
 */
import React from 'react';
import tracker from '../../../js/utils/tracker';

/**
 * WordPress dependencies
 */
const {__} = wp.i18n;

export default ({smushData}) => {

	return (
		<React.Fragment>
			<div className="sui-box-header">
				<h3 className="sui-box-title">
					{__('Local WebP', 'wp-smushit')}
				</h3>
			</div>
			<div className="sui-box-body">
				<div className="sui-message">
					<img
						className="sui-image"
						src={smushData.urls.freeImg}
						srcSet={smushData.urls.freeImg2x + ' 2x'}
						alt={__('Smush WebP', 'wp-smushit')}
					/>

					<div className="sui-message-content">
						<p>
							{__(
								'Fix the "Serve images in next-gen format" Google PageSpeed recommendation with a single click! Serve WebP images directly from your server to supported browsers, while seamlessly switching to original images for those without WebP support. All without relying on a CDN or any server configuration.',
								'wp-smushit'
							)}
						</p>

						<ol className="sui-upsell-list">
							<li>
								<span
									className="sui-icon-check sui-sm"
									aria-hidden="true"
								/>
								{__(
									'Activate the Local WebP feature with a single click; no server configuration required.',
									'wp-smushit'
								)}
							</li>
							<li>
								<span
									className="sui-icon-check sui-sm"
									aria-hidden="true"
								/>
								{__(
									'Fix “Serve images in next-gen format" Google PageSpeed recommendation.',
									'wp-smushit'
								)}
							</li>
							<li>
								<span
									className="sui-icon-check sui-sm"
									aria-hidden="true"
								/>
								{__(
									'Serve WebP version of images in the browsers that support it and fall back to JPEGs and PNGs for unsupported browsers.',
									'wp-smushit'
								)}
							</li>
						</ol>

						<p className="sui-margin-top">
							<a
								href={smushData.urls.upsell}
								className="sui-button sui-button-purple"
								style={{marginRight: '30px'}}
								target="_blank"
								rel="noreferrer"
								onClick={ () => {
									tracker.track( 'local_webp_upsell', {
										Location: 'local_webp_page',
									} );
								} }
							>
								{__('UNLOCK WEBP WITH PRO', 'wp-smushit')}
							</a>
						</p>
					</div>
				</div>
			</div>
		</React.Fragment>
	);
};
