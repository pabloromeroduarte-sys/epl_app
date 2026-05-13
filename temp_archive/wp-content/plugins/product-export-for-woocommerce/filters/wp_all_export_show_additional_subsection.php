<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function pmwpe_wp_all_export_show_additional_subsection($show, $sub_slug, $sub_section)
{

    if($sub_slug == 'attributes') {
        return false;
    }

    return true;
}