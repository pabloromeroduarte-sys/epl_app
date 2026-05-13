<?php

if(!function_exists('wp_all_import_sanitize_svg')) {
	function wp_all_import_sanitize_svg( $svg, $is_file_path = true ) {
		$sanitizer = new \enshrined\svgSanitize\Sanitizer();

		if ( $is_file_path ) {
			$svgContents  = file_get_contents( $svg );
			$sanitizedSvg = $sanitizer->sanitize( $svgContents );
			if ( $sanitizedSvg ) {
				file_put_contents( $svg, $sanitizedSvg );
			}
		}else{
			$svg = $sanitizer->sanitize( $svg );
		}

		return $svg;
	}
}