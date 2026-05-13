<?php
// Full-screen preview modal for Step 3 (Free Edition - Upgrade Notice Only)
?>

<div id="wpai-full-preview-modal" class="wpai-full-preview-modal" style="display: none;">
	<div class="wpai-full-preview-overlay">
		<div class="wpai-full-preview-container">
			<!-- Top Bar with Close Button -->
			<div class="wpai-full-preview-header">
				<div class="wpai-preview-header-spacer"></div>
				<button type="button" class="wpai-full-preview-close">
					<span class="dashicons dashicons-no-alt"></span>
				</button>
			</div>

			<!-- Tab Navigation (Disabled) -->
			<div class="wpai-full-preview-tabs">
				<button type="button" class="wpai-preview-tab" disabled>
					<span class="dashicons dashicons-admin-post"></span>
					<?php _e('WP Admin View', 'wp_all_import_plugin'); ?>
				</button>
				<button type="button" class="wpai-preview-tab" disabled>
					<span class="dashicons dashicons-visibility"></span>
					<?php _e('Frontend View', 'wp_all_import_plugin'); ?>
				</button>
				<button type="button" class="wpai-preview-tab" disabled>
					<span class="dashicons dashicons-admin-settings"></span>
					<?php _e('Preview Settings', 'wp_all_import_plugin'); ?>
				</button>
			</div>

			<!-- Upgrade Notice -->
			<div class="wpai-full-preview-content">
				<div class="wpallimport-free-edition-notice" style="margin: 0; max-width: none; border-radius: 0; border-left: none; border-right: none; text-align: center;">
					<a href="https://www.wpallimport.com/checkout/?edd_action=add_to_cart&download_id=5839966&edd_options%5Bprice_id%5D=1&discount=welcome-upgrade-99&utm_source=import-plugin-free&utm_medium=upgrade-notice&utm_campaign=preview-feature" target="_blank" class="upgrade_link"><?php _e('Upgrade to WP All Import Pro to Use the Preview Feature', 'wp_all_import_plugin');?></a>
					<p><?php _e('If you already own it, remove the free edition and install the Pro edition.', 'wp_all_import_plugin'); ?></p>
				</div>
			</div>
		</div>
	</div>
</div>

<style>
.wpai-full-preview-modal {
	position: fixed;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	z-index: 999999;
	background: rgba(0, 0, 0, 0.7);
}

.wpai-full-preview-overlay {
	width: 100%;
	height: 100%;
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 20px;
	box-sizing: border-box;
}

.wpai-full-preview-container {
	background: #fff;
	width: 95vw;
	height: 95vh;
	max-width: 1920px;
	max-height: 1080px;
	border-radius: 8px;
	box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
	display: flex;
	flex-direction: column;
	overflow: hidden;
	box-sizing: border-box;
}

.wpai-full-preview-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 15px 20px;
	background: #f8f9fa;
	border-bottom: 1px solid #ddd;
}

.wpai-preview-header-spacer {
	flex: 1;
}

.wpai-full-preview-close {
	background: transparent;
	border: none;
	cursor: pointer;
	padding: 5px;
	color: #666;
	transition: color 0.2s;
}

.wpai-full-preview-close:hover {
	color: #d63638;
}

.wpai-full-preview-close .dashicons {
	font-size: 24px;
	width: 24px;
	height: 24px;
}

.wpai-full-preview-tabs {
	display: flex;
	background: #f8f9fa;
	border-bottom: 1px solid #ddd;
	padding: 0 20px;
}

.wpai-preview-tab {
	background: transparent;
	border: none;
	padding: 12px 20px;
	cursor: not-allowed;
	display: flex;
	align-items: center;
	gap: 8px;
	color: #999;
	font-weight: 500;
	border-bottom: 3px solid transparent;
	transition: all 0.2s;
	opacity: 0.5;
}

.wpai-preview-tab:hover {
	color: #999;
	background: transparent;
}

.wpai-preview-tab.active {
	color: #999;
	border-bottom-color: transparent;
	background: transparent;
}

.wpai-preview-tab .dashicons {
	font-size: 18px;
	width: 18px;
	height: 18px;
}

.wpai-full-preview-content {
	flex: 1;
	position: relative;
	overflow: hidden;
	background: #fff;
	min-height: 0;
	box-sizing: border-box;
}

</style>

<script>
jQuery(document).ready(function($) {
	// Preview modal - Free Edition (Upgrade Notice Only)
	$('#wpai-full-preview-btn').on('click', function(e) {
		e.preventDefault();
		$('#wpai-full-preview-modal').fadeIn(200);
	});

	$('.wpai-full-preview-close').on('click', function() {
		$('#wpai-full-preview-modal').fadeOut(200);
	});

	$('.wpai-full-preview-overlay').on('click', function(e) {
		if (e.target === this) {
			$('#wpai-full-preview-modal').fadeOut(200);
		}
	});

	$(document).on('keydown', function(e) {
		if ((e.key === 'Escape' || e.keyCode === 27) && $('#wpai-full-preview-modal').is(':visible')) {
			$('#wpai-full-preview-modal').fadeOut(200);
		}
	});
});
</script>


