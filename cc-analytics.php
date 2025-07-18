<?php
/**
 * Plugin Name: Convert Cart Analytics
 * Description: Official Convert Cart Analytics WordPress plugin that transforms abandoned carts into product pages, tracks user behavior, provides detailed analytics, and optimizes your store for increased conversions and revenue.
 * Author: Convert Cart
 * Author URI: https://www.convertcart.com/
 * Version: 1.3.2
 * Tested up to: 6.5.5
 * Stable Tag: 1.3.2
 * License: GPLv2 or later
 * Tags: conversion rate optimization, conversion, revenue boost
 * Requires at least: 5.6
 * Requires PHP: 7.0
 * WC requires at least: 6.0
 * WC tested up to: 6.5.5
 * WC_HPOS: true
 *
 * @package WC_CC_Analytics
 */

namespace ConvertCart\Analytics;

defined( 'ABSPATH' ) || exit;

// HPOS compatibility declaration for WooCommerce 7.1+ and later versions
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			__FILE__,
			true
		);
	}
} );
/**
 * Add the integration to WooCommerce
 *
 * @param array $integrations Array of WooCommerce integrations.
 * @return array Updated array of integrations.
 */
function add_integration( $integrations ) {
	if ( ! defined( 'CC_PLUGIN_VERSION' ) ) {
		define( 'CC_PLUGIN_VERSION', '1.2.4' );
	}

	// Use WC() singleton instead of global $woocommerce for modern compatibility
	if ( function_exists( 'WC' ) && is_object( WC() ) ) {
		$integration_path = plugin_dir_path( __FILE__ ) . 'includes/class-wc-cc-analytics.php';

		if ( file_exists( $integration_path ) ) {
			include_once $integration_path;
			$integrations[] = 'ConvertCart\Analytics\WC_CC_Analytics';
		} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
			// phpcs:disable WordPress.PHP.DevelopmentFunctions
			error_log( 'WC_CC_Analytics integration file not found: ' . $integration_path );
			// phpcs:enable
		}
	}

	return $integrations;
}
add_filter( 'woocommerce_integrations', 'ConvertCart\Analytics\add_integration', 10 );

// Add custom cron schedule for 6 hours if not present
add_filter('cron_schedules', function ($schedules) {
	if (!isset($schedules['one_hours'])) {
		$schedules['one_hours'] = [
			'interval' => 1 * 3600,
			'display'  => __('Every 1 Hours'),
		];
	}
	return $schedules;
});
