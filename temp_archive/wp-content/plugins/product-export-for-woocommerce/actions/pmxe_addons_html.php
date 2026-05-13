<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function pmwpe_pmxe_addons_html() {
    echo "<input type='hidden' id='pmxe_woocommerce_product_addon_installed' value='1'>";
}