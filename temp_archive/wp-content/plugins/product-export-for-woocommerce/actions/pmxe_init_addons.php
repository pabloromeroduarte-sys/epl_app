<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ .'/../libraries/XmlExportWooCommerce.php';


function pmwpe_pmxe_init_addons() {

    if(!\XmlExportEngine::$woo_export) {
        \XmlExportEngine::$woo_export = new XmlExportWooCommerce();
    }

}