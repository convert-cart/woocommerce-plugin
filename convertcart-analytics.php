<?php
/**
 * Plugin Name: ConvertCart Analytics for WooCommerce
 * Plugin URI: https://convertcart.com
 * Description: Integrate ConvertCart Analytics with your WooCommerce store.
 * Version: 1.4.0
 * Author: ConvertCart
 * Author URI: https://convertcart.com
 * Text Domain: woocommerce_cc_analytics
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 8.5
 *
 * @package ConvertCart\Analytics
 */

defined('ABSPATH') || exit;

// Plugin constants
define('CONVERTCART_ANALYTICS_VERSION', '1.4.0');
define('CONVERTCART_ANALYTICS_FILE', __FILE__);
define('CONVERTCART_ANALYTICS_PATH', plugin_dir_path(__FILE__));
define('CONVERTCART_ANALYTICS_URL', plugin_dir_url(__FILE__));

// Add this right after the plugin constants, before any other code
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// Add after plugin constants
require_once CONVERTCART_ANALYTICS_PATH . 'includes/class-wc-cc-autoloader.php';
new \ConvertCart\Analytics\WC_CC_Autoloader();

/**
 * Check if WooCommerce is active
 */
function cc_is_woocommerce_active(): bool {
    $active_plugins = (array) get_option('active_plugins', []);
    if (is_multisite()) {
        $active_plugins = array_merge($active_plugins, get_site_option('active_sitewide_plugins', []));
    }
    return in_array('woocommerce/woocommerce.php', $active_plugins, true) || 
           array_key_exists('woocommerce/woocommerce.php', $active_plugins);
}

/**
 * Initialize the plugin
 */
function convertcart_analytics_init() {
    // Only initialize if WooCommerce is active
    if (!cc_is_woocommerce_active()) {
        return;
    }

    // Wait for WooCommerce to finish loading
    if (!class_exists('WC_Integration')) {
        return;
    }

    // Load the main plugin class
    require_once CONVERTCART_ANALYTICS_PATH . 'includes/class-wc-cc-analytics.php';

    // Add the integration
    add_filter('woocommerce_integrations', function($integrations) {
        if (!class_exists('ConvertCart\Analytics\WC_CC_Analytics')) {
            return $integrations;
        }
        $integrations[] = 'ConvertCart\Analytics\WC_CC_Analytics';
        return $integrations;
    });
}

// Hook into the right action for integration registration
add_action('woocommerce_loaded', 'convertcart_analytics_init');

// Register activation hook
register_activation_hook(__FILE__, 'cc_analytics_activate');

/**
 * Plugin activation hook
 */
function cc_analytics_activate() {
    if (!cc_is_woocommerce_active()) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            esc_html__('Convert Cart Analytics requires WooCommerce to be installed and active.', 'woocommerce_cc_analytics'),
            'Plugin dependency check',
            ['back_link' => true]
        );
    }
}

// Add admin notice if WooCommerce is not active
add_action('admin_notices', function() {
    if (!cc_is_woocommerce_active()) {
        echo '<div class="notice notice-error"><p>' . 
             esc_html__('Convert Cart Analytics requires WooCommerce to be installed and active.', 'woocommerce_cc_analytics') . 
             '</p></div>';
    }
});

// Add settings link to plugins page
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    $settings_link = sprintf(
        '<a href="%s">%s</a>',
        admin_url('admin.php?page=wc-settings&tab=integration&section=cc_analytics'),
        __('Settings', 'woocommerce_cc_analytics')
    );
    array_unshift($links, $settings_link);
    return $links;
});

// Register deactivation hook
register_deactivation_hook(__FILE__, 'cc_analytics_deactivate');

/**
 * Plugin deactivation hook.
 */
function cc_analytics_deactivate() {
    // Log deactivation if debug mode is enabled
    if (function_exists('wc_get_logger')) {
        wc_get_logger()->info('Convert Cart Analytics plugin deactivated', ['source' => 'convertcart-analytics']);
    }
}

// Add notice below plugin
add_action('after_plugin_row_' . plugin_basename(__FILE__), function($plugin_file, $plugin_data, $status) {
    if (!cc_is_woocommerce_active()) {
        $wp_list_table = _get_list_table('WP_Plugins_List_Table');
        $message = sprintf(
            /* translators: %s: WooCommerce */
            esc_html__('Convert Cart Analytics requires %s to be installed and active.', 'woocommerce_cc_analytics'),
            '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>'
        );
        echo '<style>
            .plugins tr[data-plugin="' . esc_attr(plugin_basename(__FILE__)) . '"] th,
            .plugins tr[data-plugin="' . esc_attr(plugin_basename(__FILE__)) . '"] td {
                box-shadow: none !important;
            }
            .plugins tr[data-plugin="' . esc_attr(plugin_basename(__FILE__)) . '"] + tr.plugin-update-tr .notice {
                background-color: #fcf0f1 !important;
                color: #cc1818 !important;
            }
        </style>';
        printf(
            '<tr class="plugin-update-tr active"><td colspan="%s" class="plugin-update colspanchange"><div class="update-message notice inline notice-error notice-alt"><p>%s</p></div></td></tr>',
            esc_attr($wp_list_table->get_column_count()),
            $message
        );
    }
}, 10, 3);

/**
 * Register the integration with WooCommerce.
 */
// function convertcart_analytics_register_integration( $integrations ) {
//     // REMOVED: \ConvertCart\Analytics\WC_CC_Autoloader::register(); // Incorrect static call
//     $integrations[] = '\ConvertCart\Analytics\WC_CC_Analytics';
//     return $integrations;
// }
// add_filter( 'woocommerce_integrations', 'convertcart_analytics_register_integration' ); // This filter is already added in convertcart_analytics_init

/**
 * REMOVED: Initialize block integration registration after plugins are loaded.
 */
// function convertcart_analytics_init_blocks_integration() { ... }
// REMOVED: add_action( 'plugins_loaded', 'convertcart_analytics_init_blocks_integration', 15 ); 