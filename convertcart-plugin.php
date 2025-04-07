<?php
/**
 * Plugin Name: Convert Cart Analytics
 * Description: Official Convert Cart Analytics WordPress plugin that transforms abandoned carts into product pages, tracks user behavior, provides detailed analytics, and optimizes your store for increased conversions and revenue.
 * Author: Convert Cart
 * Author URI: https://www.convertcart.com/
 * Version: 1.3.3
 * Tested up to: 6.5.5
 * Stable Tag: 1.2.4
 * License: GPLv2 or later
 * Tags: conversion rate optimization, conversion, revenue boost
 * WC requires at least: 3.0.0
 * WC tested up to: 8.5.2
 *
 * @package ConvertCart\Analytics
 */

namespace ConvertCart\Analytics;

defined( 'ABSPATH' ) || exit;

// Define plugin constants.
if ( ! defined( 'CC_PLUGIN_VERSION' ) ) {
	define( 'CC_PLUGIN_VERSION', '1.3.3' );
}

// Define plugin path constants.
if ( ! defined( 'CC_PLUGIN_PATH' ) ) {
	define( 'CC_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
}

// Define plugin URL constants.
if ( ! defined( 'CC_PLUGIN_URL' ) ) {
	define( 'CC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

// Autoload classes.
spl_autoload_register(
	function ( $class_name ) {
		// Check if the class is in our namespace.
		if ( strpos( $class_name, 'ConvertCart\\Analytics\\' ) !== 0 ) {
			return;
		}

		// Remove namespace prefix.
		$class_path = str_replace( 'ConvertCart\\Analytics\\', '', $class_name );

		// Convert class name format to file name format.
		$class_path = strtolower( str_replace( '_', '-', $class_path ) );
		$class_path = str_replace( '\\', '/', $class_path );

		// Build the file path.
		$file = CC_PLUGIN_PATH . 'includes/' . $class_path . '.php';

		// If the file exists, require it.
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

use ConvertCart\Analytics\WC_CC_Analytics; // Ensure this use statement is present

/**
 * Check if WooCommerce is active
 */
function is_woocommerce_active() {
	return in_array(
		'woocommerce/woocommerce.php',
		apply_filters('active_plugins', get_option('active_plugins'))
	);
}

/**
 * Prevent plugin activation if WooCommerce is not active
 */
function activation_check() {
	if (!is_woocommerce_active()) {
		return false;
	}
	return true;
}

/**
 * Handle plugin activation via AJAX
 */
function handle_plugin_activation() {
	if (!current_user_can('activate_plugins')) {
		wp_die(__('You do not have sufficient permissions to activate plugins.', 'woocommerce_cc_analytics'));
	}

	check_ajax_referer('activate-plugin_' . plugin_basename(__FILE__));

	if (!activation_check()) {
		$message = '';
		if (!is_woocommerce_active()) {
			$message = sprintf(
				__('Convert Cart Analytics requires WooCommerce to be installed and active. Please install and activate %s before activating Convert Cart Analytics, try activating this plugin after that.', 'woocommerce_cc_analytics'),
				'<a href="https://wordpress.org/plugins/woocommerce/" target="_blank">WooCommerce</a>'
			);
		}
		wp_send_json_error(array(
			'message' => $message
		));
	}

	// If WooCommerce is active, proceed with activation
	activate_plugin(plugin_basename(__FILE__));
	wp_send_json_success();
}
add_action('wp_ajax_convert_cart_activate_plugin', 'ConvertCart\Analytics\handle_plugin_activation');

/**
 * Modify plugin action links to handle activation via AJAX
 */
function modify_plugin_action_links($actions, $plugin_file) {
	if (plugin_basename(__FILE__) === $plugin_file && isset($actions['activate'])) {
		$nonce = wp_create_nonce('activate-plugin_' . $plugin_file);
		$actions['activate'] = sprintf(
			'<a href="#" class="button activate-now" data-plugin="%s" data-nonce="%s">%s</a>',
			esc_attr($plugin_file),
			esc_attr($nonce),
			esc_html__('Activate', 'woocommerce_cc_analytics')
		);

		// Add inline script for handling activation
		add_action('admin_footer', function() use ($plugin_file) {
			?>
			<script>
			jQuery(document).ready(function($) {
				$('.activate-now[data-plugin="<?php echo esc_js($plugin_file); ?>"]').on('click', function(e) {
					e.preventDefault();
					var $button = $(this);

					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'convert_cart_activate_plugin',
							_ajax_nonce: $button.data('nonce'),
						},
						success: function(response) {
							if (response.success) {
								window.location.reload();
							} else {
								alert(response.data.message);
							}
						}
					});
				});
			});
			</script>
			<?php
		});
	}
	return $actions;
}
add_filter('plugin_action_links', 'ConvertCart\Analytics\modify_plugin_action_links', 10, 2);

/**
 * Load dependencies.
 * This function now only ensures classes are available if not autoloaded.
 */
function load_convertcart_dependencies() {
	// Check if WooCommerce is active before proceeding
	if ( ! is_woocommerce_active() ) {
		return;
	}

	// Include the main integration class file if not autoloaded
	if ( ! class_exists( 'ConvertCart\Analytics\WC_CC_Analytics' ) ) {
		require_once CC_PLUGIN_PATH . 'includes/class-wc-cc-analytics.php';
	}
	// Include Base_Consent if not autoloaded
	if ( ! class_exists( 'ConvertCart\Analytics\Consent\Base_Consent' ) ) {
		require_once CC_PLUGIN_PATH . 'includes/consent/class-base-consent.php';
	}
	// Include SMS_Consent if not autoloaded
	if ( ! class_exists( 'ConvertCart\Analytics\Consent\SMS_Consent' ) ) {
		require_once CC_PLUGIN_PATH . 'includes/consent/class-sms-consent.php';
	}
	// Include Email_Consent if not autoloaded
	if ( ! class_exists( 'ConvertCart\Analytics\Consent\Email_Consent' ) ) {
		require_once CC_PLUGIN_PATH . 'includes/consent/class-email-consent.php';
	}
	// Include Core Integration if needed and not autoloaded
	if ( ! class_exists( 'ConvertCart\Analytics\Core\Integration' ) ) {
	    // Assuming the path, adjust if necessary
		require_once CC_PLUGIN_PATH . 'includes/core/class-integration.php';
	}

	// REMOVE the manual instantiation:
	// $GLOBALS['convertcart_analytics_integration'] = new WC_CC_Analytics();
	// error_log('Convert Cart: WC_CC_Analytics class instantiated.');
}

// Hook the dependency loader to plugins_loaded
add_action( 'plugins_loaded', 'ConvertCart\Analytics\load_convertcart_dependencies', 11 ); // Priority 11 to run after WC potentially

/**
 * Add the integration class name to WooCommerce.
 * WooCommerce will instantiate it.
 *
 * @param array $integrations Existing integrations.
 * @return array Updated integrations.
 */
function add_convertcart_integration( $integrations ) {
	// Check if the WC_CC_Analytics class exists before adding its name
	if ( class_exists( 'ConvertCart\Analytics\WC_CC_Analytics' ) ) {
		// Ensure ONLY WC_CC_Analytics is added here
		$integrations[] = 'ConvertCart\Analytics\WC_CC_Analytics';
		error_log('Convert Cart: Adding WC_CC_Analytics class name to WC Integrations.');
	} else {
		error_log('Convert Cart: ERROR - WC_CC_Analytics class not found when trying to add to WC Integrations.');
	}
	// DO NOT add Core\Integration here
	return $integrations;
}
// Ensure the filter call uses the correct namespaced function name
add_filter( 'woocommerce_integrations', 'ConvertCart\Analytics\add_convertcart_integration' );

/**
 * Add a more informative admin notice if WooCommerce gets deactivated while our plugin is active
 */
function admin_notice_missing_woocommerce() {
	if (!is_woocommerce_active()) {
		$message = sprintf(
			__('Convert Cart Analytics requires WooCommerce to be installed and active. Please install and activate %s before activating Convert Cart Analytics, try activating this plugin after that.', 'woocommerce_cc_analytics'),
			'<a href="https://wordpress.org/plugins/woocommerce/" target="_blank">WooCommerce</a>'
		);
		?>
		<tr class="plugin-update-tr active">
			<td colspan="4" class="plugin-update colspanchange">
				<div class="update-message notice inline notice-error notice-alt">
					<p><?php echo wp_kses_post($message); ?></p>
				</div>
			</td>
		</tr>
		<style>
			.plugins tr[data-plugin='<?php echo esc_attr(plugin_basename(__FILE__)); ?>'] th,
			.plugins tr[data-plugin='<?php echo esc_attr(plugin_basename(__FILE__)); ?>'] td {
				box-shadow: none;
			}
		</style>
		<?php
		deactivate_plugins(plugin_basename(__FILE__));
	}
}

// Add notice to plugin row
add_action('after_plugin_row_' . plugin_basename(__FILE__), 'ConvertCart\Analytics\admin_notice_missing_woocommerce', 10, 2);

// Add HPOS compatibility declaration
add_action('before_woocommerce_init', function() {
	if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
	}
});

// Add this near the top of the file, after the plugin header
add_action('plugins_loaded', function() {
    error_log('Convert Cart: Plugin loaded');
});

// Add this to test if WooCommerce hooks are available
add_action('init', function() {
    error_log('Convert Cart: Init hook fired');
    error_log('Convert Cart: WooCommerce class exists: ' . (class_exists('WooCommerce') ? 'yes' : 'no'));
});

// Keep the wp_head action for CSS
add_action('wp_head', function() {
    ?>
    <style type="text/css">
        .woocommerce-checkout .consent-checkbox {
            margin: 1em 0;
            padding: 0.5em;
            background: #f8f8f8;
            border-radius: 3px;
        }
        .woocommerce-checkout .consent-checkbox label {
            display: flex !important;
            align-items: center;
            gap: 0.5em;
            cursor: pointer;
        }
        .woocommerce-checkout .consent-checkbox input[type="checkbox"] {
            margin: 0 !important;
        }
    </style>
    <?php
});

// Keep the template_redirect check
add_action('template_redirect', function() {
    if (is_checkout()) {
        error_log('Convert Cart: On checkout page');
        // Log the template only once to reduce noise
        static $logged_template = false;
        if (!$logged_template) {
             error_log('Convert Cart: Current template: ' . get_page_template());
             $logged_template = true;
        }
        // error_log('Convert Cart: Available hooks in checkout: ' . print_r(array_keys($GLOBALS['wp_filter']), true)); // Maybe comment this out too, it's very long
    }
});
