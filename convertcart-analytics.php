<?php
/**
 * Plugin Name: Convert Cart Analytics
 * Plugin URI: 
 * Description: Official Convert Cart Analytics WordPress plugin that transforms abandoned carts into product pages, tracks user behavior, provides detailed analytics, and optimizes your store for increased conversions and revenue.
 * Version: 1.4.0
 * Author: Convert Cart
 * Author URI: https://convertcart.com
 * Text Domain: woocommerce_cc_analytics
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 * License: Proprietary
 * License URI: https://www.convertcart.com/terms-of-use
 *
 * @package ConvertCart\Analytics
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Prevent direct file access
if (!defined('WPINC')) {
    die;
}

// Define constants
define('CONVERTCART_ANALYTICS_VERSION', '1.4.0');
define('CONVERTCART_ANALYTICS_PATH', plugin_dir_path(__FILE__));
define('CONVERTCART_ANALYTICS_URL', plugin_dir_url(__FILE__));
define('CONVERTCART_PLUGIN_FILE', __FILE__);

/**
 * Check if WooCommerce is active
 *
 * @return bool
 */
function cc_is_woocommerce_active(): bool {
    $active_plugins = (array) get_option('active_plugins', []);
    if (is_multisite()) {
        $active_plugins = array_merge($active_plugins, get_site_option('active_sitewide_plugins', []));
    }
    
    return in_array('woocommerce/woocommerce.php', $active_plugins, true) || 
           array_key_exists('woocommerce/woocommerce.php', $active_plugins);
}

// Only load the plugin functionality if WooCommerce is active
if (cc_is_woocommerce_active()) {
    // Declare HPOS compatibility
    add_action('before_woocommerce_init', function() {
        if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    });

    // Autoloader
    require_once CONVERTCART_ANALYTICS_PATH . 'includes/class-wc-cc-autoloader.php';
    new \ConvertCart\Analytics\WC_CC_Autoloader();

    // Add the integration to WooCommerce
    add_filter('woocommerce_integrations', 'wc_cc_analytics_add_integration');

    // Initialize the plugin
    add_action('plugins_loaded', function() {
        load_plugin_textdomain('woocommerce_cc_analytics', false, dirname(plugin_basename(__FILE__)) . '/languages/');

        if (!class_exists('WooCommerce')) {
            error_log("ConvertCart ERROR: WooCommerce not active - plugin disabled.");
            return;
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
}

// Register activation/deactivation hooks
register_activation_hook(__FILE__, 'cc_analytics_activate');
register_deactivation_hook(__FILE__, 'cc_analytics_deactivate');

/**
 * Plugin activation hook.
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
    
    // Ensure proper version requirements
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            esc_html__('Convert Cart Analytics requires PHP 7.4 or higher.', 'woocommerce_cc_analytics'),
            'Version check failed',
            ['back_link' => true]
        );
    }
}

/**
 * Plugin deactivation hook.
 */
function cc_analytics_deactivate() {
    // Log deactivation if debug mode is enabled
    if (function_exists('wc_get_logger')) {
        wc_get_logger()->info('Convert Cart Analytics plugin deactivated', ['source' => 'convertcart-analytics']);
    }
}

/**
 * Add the integration to WooCommerce.
 *
 * @param array $integrations WooCommerce integrations.
 * @return array
 */
function wc_cc_analytics_add_integration(array $integrations): array {
    if (class_exists('ConvertCart\\Analytics\\WC_CC_Analytics')) {
        $integrations[] = 'ConvertCart\\Analytics\\WC_CC_Analytics';
    } else {
        // Log critical error if main class is missing
        error_log("ConvertCart ERROR: Analytics integration class not found!");
    }
    return $integrations;
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