<?php
/**
 * Plugin Name: Convert Cart Analytics
 * Description: Official Convert Cart Analytics WordPress plugin that transforms abandoned carts into product pages, tracks user behavior, provides detailed analytics, and optimizes your store for increased conversions and revenue.
 * Author: Convert Cart
 * Author URI: https://www.convertcart.com/
 * Version: 1.2.4
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
	define( 'CC_PLUGIN_VERSION', '1.2.4' );
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
 * Initialize the plugin.
 */
function init() {
	// Check if WooCommerce is active
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function() {
			?>
			<div class="error">
				<p><?php esc_html_e( 'Convert Cart Analytics requires WooCommerce to be installed and active.', 'woocommerce_cc_analytics' ); ?></p>
			</div>
			<?php
		});
		return;
	}

	// Now it's safe to include and use WooCommerce classes
	require_once dirname( __FILE__ ) . '/includes/core/class-integration.php';

	// Add the integration to WooCommerce.
	add_filter( 'woocommerce_integrations', 'ConvertCart\Analytics\add_integration', 10 );
}
add_action( 'plugins_loaded', 'ConvertCart\Analytics\init' );

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

	$integration_path = plugin_dir_path( __FILE__ ) . 'includes/core/class-integration.php';

	if ( file_exists( $integration_path ) ) {
		include_once $integration_path;
		$integrations[] = 'ConvertCart\Analytics\Core\Integration';
	} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
		// phpcs:disable WordPress.PHP.DevelopmentFunctions
		error_log( 'ConvertCart integration file not found: ' . $integration_path );
		// phpcs:enable
	}

	return $integrations;
}
