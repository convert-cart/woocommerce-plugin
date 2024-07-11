<?php
/**
 * Plugin Name: Convert Cart Analytics
 * Plugin URI: http://www.convertcart.com
 * Description: Official WooCommerce Plugin Of Convert Cart Analytics
 * Author: Convert Cart
 * Author URI: http://www.convertcart.com
 * Version: 1.2.3
 *
 * @package WC_CC_Analytics
 */

namespace ConvertCart\Analytics;

defined( 'ABSPATH' ) || exit;

/**
 * Add the integration to WooCommerce
 *
 * @param array $integrations Array of WooCommerce integrations.
 * @return array Updated array of integrations.
 */
function add_integration( $integrations ) {
    // Define plugin version constant.
    if ( ! defined( 'CC_PLUGIN_VERSION' ) ) {
        define( 'CC_PLUGIN_VERSION', '1.2.3' );
    }

    global $woocommerce;
    if ( is_object( $woocommerce ) ) {
        $integration_path = plugin_dir_path( __FILE__ ) . 'includes/class-wc-cc-analytics.php';

        if ( file_exists( $integration_path ) ) {
            include_once $integration_path;
            $integrations[] = 'WC_Cc_Analytics';
        } else {
            error_log( 'WC_CC_Analytics integration file not found: ' . $integration_path );
        }
    }

    return $integrations;
}
add_filter( 'woocommerce_integrations', 'ConvertCart\\Analytics\\add_integration', 10 );

