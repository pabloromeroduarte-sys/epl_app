<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function pmwpe_wp_all_export_before_available_subsection($sub_slug, $sub_section)
{
    if ($sub_slug === 'attributes' && PMXE_EDITION === 'free') {
        echo '<ul>' .
            '<li class="available_sub_section">' .
                '<div style="display: block;" class="wpallexport_disabled wpallexport-free-edition-notice">' .
                    '<a class="upgrade_link" target="_blank" href="https://www.wpallimport.com/checkout/?edd_action=add_to_cart&download_id=5839955&discount=welcome-upgrade-169&edd_options%5Bprice_id%5D=1&utm_source=export-plugin-free&utm_medium=upgrade-notice&utm_campaign=export-product-attributes">' .
                        'Purchase the WooCommerce Export Package to export Attributes.' .
                    '</a>' .
                '</div>' .
            '</li>' .
        '</ul>';
    }

    if ($sub_slug === 'attributes' && PMXE_EDITION === 'paid') {
        echo '<ul>' .
            '<li class="available_sub_section">' .
                '<div style="display: block;" class="wpallexport_disabled wpallexport-free-edition-notice">' .
                    '<a class="upgrade_link" target="_blank" href="https://www.wpallimport.com/portal/discounts/?utm_source=export-plugin-pro&utm_medium=upgrade-notice&utm_campaign=export-product-attributes">' .
                        'Purchase the WooCommerce Export Add-On to export Attributes.' .
                    '</a>' .
                '</div>' .
            '</li>' .
        '</ul>';
    }

}