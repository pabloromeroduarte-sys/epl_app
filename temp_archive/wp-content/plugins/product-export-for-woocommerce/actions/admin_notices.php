<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function pmwpe_admin_notices() {
	// notify user if history folder is not writable
	if ( ! class_exists( 'PMXE_Plugin' ) ) {
		?>
		<div class="error"><p>
			<?php

			echo wp_kses_post(
				sprintf(
                    /* translators: %s: Plugin name */
					__('<b>%s Plugin</b>: WP All Export must be installed and activated.</a>', 'wpae-wooco-product-export-addon'),
					esc_html(PMWPE_Plugin::getInstance()->getName())
				)
			);
			?>
		</p></div>
		<?php

		deactivate_plugins( PMWPE_ROOT_DIR . '/wpae-woocommerce-product-add-on.php');
		return;

	}

    // If the pro wooco add-on is active
    if ( class_exists('PMWE_Plugin') ) {
        ?>
        <div class="error"><p>
                <?php echo esc_html__('The WooCommerce Export Add-On Pro is already activated. The WooCommerce Product Export Add-On can not be used at the same time and has been deactivated', 'wpae-wooco-product-export-addon'); ?>
            </p></div>
        <?php

        deactivate_plugins( PMWPE_ROOT_DIR . '/wpae-woocommerce-product-add-on.php');
        return;

    }

	// If an unsupported WPAE version is active
	if( class_exists('PMXE_Plugin')){
		if( (PMXE_EDITION == "free" && version_compare(PMXE_VERSION, '1.3.1', '<=')) || (PMXE_EDITION == "paid" && version_compare(PMXE_VERSION, '1.7.1', '<=')) ){

				?>
                <div class="error"><p>
						<?php echo esc_html__('Update to the latest version of WP All Export to use the WooCommerce Product Export Add-On', 'wpae-wooco-product-export-addon');
						?>
                    </p></div>
				<?php

				deactivate_plugins( PMWPE_ROOT_DIR . '/wpae-woocommerce-product-add-on.php');
		}
	}

	// Check for admin notices from GET parameter
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only, no action taken
	if ( isset( $_GET['pmwpe_nt'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via wp_kses_post below
		$messages = wp_unslash( $_GET['pmwpe_nt'] );
		is_array($messages) or $messages = array($messages);
		foreach ($messages as $type => $m) {
			in_array((string)$type, array('updated', 'error')) or $type = 'updated';
			?>
			<div class="<?php echo esc_attr($type) ?>"><p><?php echo wp_kses_post($m) ?></p></div>
			<?php
		}
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only, no action taken
	if ( ! empty($_GET['type']) and sanitize_key($_GET['type']) == 'user'){
		?>
		<script type="text/javascript">
			(function($){$(function () {
				$('#toplevel_page_pmxi-admin-home').find('.wp-submenu').find('li').removeClass('current');
				$('#toplevel_page_pmxi-admin-home').find('.wp-submenu').find('a').removeClass('current');
				$('#toplevel_page_pmxi-admin-home').find('.wp-submenu').find('li').eq(2).addClass('current').find('a').addClass('current');
			});})(jQuery);
		</script>
		<?php
	}
}
