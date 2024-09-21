<?php
if (!defined('WORDFENCE_VERSION')) { exit; }
$errorMessage = null;
if (!$shouldShowOnboarding) {
	$errorMessage = __('Wordfence is already installed on this site. If you need to replace the current license, you may do so by visiting the "All Options" page of the Wordfence menu.', 'wordfence');
}
elseif ($invalidLink) {
	if ($payloadException instanceof wfWebsiteEphemeralPayloadRateLimitedException) {
		$errorMessage = __('Too many installation requests have been made from your IP address. Please try again later.', 'wordfence');
	}
	elseif ($payloadException instanceof wfWebsiteEphemeralPayloadExpiredException) {
		$errorMessage = __('The link you used to access this page has expired, has already been used, or is otherwise invalid.', 'wordfence');
	}
	else {
		$errorMessage = __('An error occurred while retrieving your license information from the Wordfence servers. Please ensure that your server can reach www.wordfence.com on port 443.', 'wordfence');
	}
}
?>
<div class="wrap wordfence" id="wf-install">
	<div class="wf-container-fluid">
		<div class="wf-row">
			<div class="wf-col-xs-12">
				<div class="wp-header-end"></div>
				<?php
				echo wfView::create('common/section-title', array(
					'title' => __('Install Wordfence', 'wordfence'),
					'showIcon' => true,
				))->render();
				?>
			</div>
		</div>
		<div class="wf-row">
			<div class="wf-col-xs-12">
				<?php if ($errorMessage): ?>
					<p class="wf-onboarding-error"><?php echo esc_html($errorMessage) ?></p>
				<?php endif ?>
				<?php if ($shouldShowOnboarding): ?>
					<?php echo wfView::create('onboarding/registration-prompt', array('attempt' => 1, 'existing' => true, 'email' => $email, 'license' => $license)) ?>
				<?php endif ?>
			</div>
		</div>
	</div>
</div>