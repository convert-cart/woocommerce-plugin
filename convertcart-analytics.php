<?php
if ( ! defined( 'CONVERTCART_PLUGIN_FILE' ) ) {
    define( 'CONVERTCART_PLUGIN_FILE', __FILE__ );
}
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
        error_log('[ConvertCart DEBUG] woocommerce_integrations filter running.');
        $class_file = CONVERTCART_ANALYTICS_PATH . 'includes/class-wc-cc-analytics.php';
        if (!class_exists('ConvertCart\\Analytics\\WC_CC_Analytics')) {
            if (file_exists($class_file)) {
                require_once $class_file;
                error_log('[ConvertCart DEBUG] Required class-wc-cc-analytics.php manually.');
            } else {
                error_log('[ConvertCart DEBUG] class-wc-cc-analytics.php file not found!');
            }
        }
        if (class_exists('ConvertCart\\Analytics\\WC_CC_Analytics')) {
            error_log('[ConvertCart DEBUG] About to instantiate WC_CC_Analytics.');
            $instance = new \ConvertCart\Analytics\WC_CC_Analytics();
            error_log('[ConvertCart DEBUG] Instantiated WC_CC_Analytics: ' . print_r($instance, true));
            $integrations[] = $instance;
            error_log('[ConvertCart DEBUG] Registered ConvertCart\\Analytics\\WC_CC_Analytics integration (as object).');
        } else {
            error_log('[ConvertCart DEBUG] WC_CC_Analytics class STILL not loaded after require!');
        }
        // Log all integration types
        foreach ($integrations as $i) {
            error_log('[ConvertCart DEBUG] Integration in array: ' . (is_object($i) ? get_class($i) : gettype($i)));
        }
        return $integrations;
    });

}

// Hook into the right action for integration registration
add_action('woocommerce_loaded', 'convertcart_analytics_init');

// Register Checkout Block Integration with WooCommerce Blocks only after WooCommerce is fully initialized
add_action(
    'woocommerce_init',
    function () {
        add_action(
            'woocommerce_blocks_checkout_block_registration',
            function ($integration_registry) {
                require_once __DIR__ . '/includes/blocks/class-checkout-block-integration.php';
                // Get the plugin instance if needed
                $plugin = null;
                error_log('[ConvertCart DEBUG] (Block Reg) About to check for registered integrations... (A)');
                if (function_exists('wc')) {
                    error_log('[ConvertCart DEBUG] (Block Reg) wc() exists. (B)');
                    $wc_object = wc();
                    if ($wc_object) {
                        error_log('[ConvertCart DEBUG] (Block Reg) wc() returns object of class: ' . get_class($wc_object) . ' (C)');
                        global $woocommerce;
                        if (isset($woocommerce) && isset($woocommerce->integrations)) {
                            error_log('[ConvertCart DEBUG] (Block Reg) Using global $woocommerce->integrations for lookup.');
                            $integrations = $woocommerce->integrations->get_integrations();
                            error_log('[ConvertCart DEBUG] Raw integrations array: ' . print_r($integrations, true));
                            if (is_array($integrations) && count($integrations) === 0) {
                                error_log('[ConvertCart DEBUG] Integrations array is EMPTY.');
                            }
                            error_log('[ConvertCart DEBUG] Integration IDs: ' . print_r(array_map(function($i) { return method_exists($i, 'get_id') ? $i->get_id() : get_class($i); }, $integrations), true));
                            foreach ($integrations as $integration) {
                                if (!is_object($integration)) {
                                    if (is_string($integration) && class_exists($integration) && is_subclass_of($integration, 'WC_Integration')) {
                                        $integration = new $integration();
                                    } else {
                                        error_log('[ConvertCart DEBUG] Integration entry is not an object: ' . print_r($integration, true));
                                        continue;
                                    }
                                }
                                if (method_exists($integration, 'get_id')) {
                                    $id = $integration->get_id();
                                    error_log('[ConvertCart DEBUG] Checking integration with get_id(): ' . $id);
                                    if ($id === 'cc_analytics') {
                                        $plugin = $integration;
                                        error_log('[ConvertCart DEBUG] Found cc_analytics integration via global $woocommerce. Plugin instance: ' . print_r($plugin, true));
                                        break;
                                    }
                                } else {
                                    error_log('[ConvertCart DEBUG] Integration object does not have get_id(): ' . get_class($integration));
                                }
                            }
                        } else {
                            error_log('[ConvertCart DEBUG] (Block Reg) $woocommerce->integrations not available.');
                        }
                    } else {
                        error_log('[ConvertCart DEBUG] (Block Reg) wc() returns null/false. (F)');
                    }
                } else {
                    error_log('[ConvertCart DEBUG] (Block Reg) function wc() does NOT exist. (G)');
                }
                error_log('[ConvertCart DEBUG] (Block Reg) About to log (H)');
                error_log('[ConvertCart DEBUG] Finished attempting to get plugin instance. (H)');
                if ($plugin) {
                    error_log('[ConvertCart DEBUG] About to instantiate Checkout_Block_Integration with plugin instance.');
                    $integration = new \ConvertCart\Analytics\Blocks\Checkout_Block_Integration($plugin);
                    if (!$integration_registry->is_registered($integration->get_name())) {
                        $integration_registry->register($integration);
                        error_log('[ConvertCart DEBUG] Successfully registered checkout block integration.');
                    } else {
                        error_log('[ConvertCart DEBUG] Integration already registered: ' . $integration->get_name());
                    }
                } else {
                    error_log('[ConvertCart Blocks ERROR] Could not get valid plugin instance for Checkout_Block_Integration. Block integration not registered.');
                }
            }
        );
    }
);

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