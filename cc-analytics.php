<?php
/**
 * Plugin Name: Convert Cart Analytics
 * Plugin URI: http://www.convertcart.com
 * Description: Official Woo Commerce Plugin Of Convert Cart Analytics
 * Author: Aamir
 * Author URI: http://www.convertcart.com
 * Version: 1.1.4
 */

/**
 * Add the integration to WooCommerce
 */
function wc_cc_analytics( $integrations ) {
	global $woocommerce;

	if ( is_object( $woocommerce ) ) {
		include_once( 'includes/class-cc-analytics.php' );
		$integrations[] = 'WC_Cc_Analytics';
	}
	return $integrations;
}
add_filter( 'woocommerce_integrations', 'wc_cc_analytics', 10 );
