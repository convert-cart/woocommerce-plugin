<?php
/**
 * Plugin Name: Convert Cart Analytics
 * Description: Official Convert Cart Analytics WordPress plugin that transforms abandoned carts into product pages, tracks user behavior, provides detailed analytics, and optimizes your store for increased conversions and revenue.
 * Author: Convert Cart
 * Author URI: https://www.convertcart.com/
 * Version: 1.3.0
 * Tested up to: 6.5.5
 * Stable Tag: 1.3.0
 * License: GPLv2 or later
 * Tags: conversion rate optimization, conversion, revenue boost
 *
 * @package WC_CC_Analytics
 */

namespace ConvertCart\Analytics;

defined('ABSPATH') || exit;

/**
 * Add the integration to WooCommerce
 *
 * @param array $integrations Array of WooCommerce integrations.
 * @return array Updated array of integrations.
 */
function add_integration($integrations)
{
    if (! defined('CC_PLUGIN_VERSION')) {
        define('CC_PLUGIN_VERSION', '1.3.0');
    }

    global $woocommerce;
    if (is_object($woocommerce)) {
        $integration_path = plugin_dir_path(__FILE__) . 'includes/class-wc-cc-analytics.php';

        if (file_exists($integration_path)) {
            include_once $integration_path;
            $integrations[] = 'ConvertCart\Analytics\WC_CC_Analytics';
        } elseif (defined('WP_DEBUG') && WP_DEBUG === true) {
            // phpcs:disable WordPress.PHP.DevelopmentFunctions
            error_log('WC_CC_Analytics integration file not found: ' . $integration_path);
            // phpcs:enable
        }
    }

    return $integrations;
}
add_filter('woocommerce_integrations', 'ConvertCart\Analytics\add_integration', 10);
