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
 * Initialize the plugin.
 */
function init() {
	// Now it's safe to include and use WooCommerce classes
	require_once dirname( __FILE__ ) . '/includes/core/class-integration.php';

	// Add the integration to WooCommerce.
	add_filter( 'woocommerce_integrations', 'ConvertCart\Analytics\add_integration', 10 );
}

/**
 * Add the integration to WooCommerce.
 *
 * @param array $integrations Array of WooCommerce integrations.
 * @return array Updated array of integrations.
 */
function add_integration( $integrations ) {
	// Check if WooCommerce is active.
	if ( ! class_exists( 'WooCommerce' ) ) {
		return $integrations;
	}

	$integrations[] = 'ConvertCart\Analytics\Core\Integration';

	return $integrations;
}

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

// Initialize plugin only if WooCommerce is active
if (is_woocommerce_active()) {
	add_action('plugins_loaded', 'ConvertCart\Analytics\init');
}

// Add HPOS compatibility declaration
add_action('before_woocommerce_init', function() {
	if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
	}
});
