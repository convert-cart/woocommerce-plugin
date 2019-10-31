<?php
/**
 * Plugin Name: Convert Cart Analytics
 * Plugin URI: http://www.convertcart.com
 * Description: Official Woo Commerce Plugin Of Convert Cart Analytics
 * Author: Aamir
 * Author URI: http://www.convertcart.com
 * Version: 1.2.1
 *
 * @package  WC_CC_Analytics
 */

/**
 * Add the integration to WooCommerce
 *
 * @param array $integrations .
 */
function wc_cc_analytics( $integrations ) {
	// when updating version, update both above comment and below constant.
	define( 'CC_PLUGIN_VERSION', '1.2.1' ); // used to include version in metaData of events.

	global $woocommerce;
	if ( is_object( $woocommerce ) ) {
		include_once 'includes/class-wc-cc-analytics.php';
		$integrations[] = 'WC_Cc_Analytics';
	}
	return $integrations;
}
add_filter( 'woocommerce_integrations', 'wc_cc_analytics', 10 );
