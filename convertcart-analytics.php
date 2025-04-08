<?php
/**
 * Plugin Name: Convert Cart Analytics
 * Plugin URI: https://convertcart.com
 * Description: Convert Cart Analytics integration for WooCommerce
 * Version: 1.0.0
 * Author: Convert Cart
 * Author URI: https://convertcart.com
 * Text Domain: woocommerce_cc_analytics
 * Domain Path: /languages/
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 *
 * @package ConvertCart\Analytics
 */

defined('ABSPATH') || exit;

define('CONVERTCART_VERSION', '1.0.0');
define('CONVERTCART_PLUGIN_FILE', __FILE__);
define('CONVERTCART_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

// Declare HPOS compatibility
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// Autoloader
require_once dirname(__FILE__) . '/includes/class-wc-cc-autoloader.php';
new \ConvertCart\Analytics\WC_CC_Autoloader();

// Debug code
$admin_file = dirname(__FILE__) . '/includes/admin/class-wc-cc-admin.php';
error_log("ConvertCart Debug: Admin file exists: " . (file_exists($admin_file) ? 'yes' : 'no'));
error_log("ConvertCart Debug: Admin file path: " . $admin_file);

/**
 * Add the integration to WooCommerce.
 *
 * @param array $integrations WooCommerce integrations.
 * @return array
 */
function wc_cc_analytics_add_integration(array $integrations): array {
    error_log("ConvertCart Debug (Plugin File): convertcart_analytics_add_integration filter hook fired.");

    if (class_exists('ConvertCart\\Analytics\\WC_CC_Analytics')) {
        $integrations[] = 'ConvertCart\\Analytics\\WC_CC_Analytics';
        error_log("ConvertCart Debug (Plugin File): Added ConvertCart\\Analytics\\WC_CC_Analytics to integrations.");
    } else {
        error_log("ConvertCart Debug (Plugin File): ERROR - Class ConvertCart\\Analytics\\WC_CC_Analytics not found!");
    }
    return $integrations;
}
add_filter('woocommerce_integrations', 'wc_cc_analytics_add_integration');

// Initialize the plugin
add_action('plugins_loaded', function() {
    load_plugin_textdomain('woocommerce_cc_analytics', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    error_log("ConvertCart Debug (Plugin File): convertcart_analytics_init action hook fired.");

    if (!class_exists('WooCommerce')) {
        error_log("ConvertCart Debug (Plugin File): WooCommerce not active, ConvertCart Analytics plugin not fully loaded.");
        return;
    }

    add_filter('woocommerce_integrations', 'wc_cc_analytics_add_integration');
}); 