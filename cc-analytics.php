<?php
/**
 * Plugin Name: Convert Cart Analytics
 * Description: Official Convert Cart Analytics WordPress plugin that tracks user behavior, transforms abandoned carts into product pages, and optimizes your store for conversions.
 * Author: Convert Cart
 * Author URI: https://www.convertcart.com/
 * Version: 1.4.1-beta
 * Tested up to: 6.5.5
 * Stable Tag: 1.4.1-beta
 * License: GPLv2 or later
 * Tags: conversion rate optimization, conversion, revenue boost
 * Requires at least: 5.6
 * Requires PHP: 7.0
 * WC requires at least: 6.0
 * WC tested up to: 6.5.5
 * WC_HPOS: true
 */

defined( 'ABSPATH' ) || exit;

require_once plugin_dir_path( __FILE__ ) . 'includes/class-cc-consent-manager.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-cc-event-tracker.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-cc-discount-manager.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-cc-admin-ui.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-cc-rest-api.php';

// Define plugin constants for reliable URL construction
if ( ! defined( 'CONVERTCART_PLUGIN_URL' ) ) {
	define( 'CONVERTCART_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'CONVERTCART_PLUGIN_PATH' ) ) {
	define( 'CONVERTCART_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
}

// Debug: Log plugin URLs (remove in production)
if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
	error_log( 'ConvertCart Plugin URL: ' . CONVERTCART_PLUGIN_URL );
	error_log( 'ConvertCart Plugin Path: ' . CONVERTCART_PLUGIN_PATH );
}

// Declare HPOS compatibility
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				__FILE__,
				true
			);
		}
	}
);

// Register legacy integration
function add_integration( $integrations ) {
	if ( ! defined( 'CC_PLUGIN_VERSION' ) ) {
		define( 'CC_PLUGIN_VERSION', '1.2.4' );
	}

	if ( function_exists( 'WC' ) && is_object( WC() ) ) {
		$integration_path = plugin_dir_path( __FILE__ ) . 'includes/class-wc-cc-analytics.php';
		if ( file_exists( $integration_path ) ) {
			include_once $integration_path;
			$integrations[] = 'ConvertCart\\Analytics\\WC_CC_Analytics';
		} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
			error_log( 'WC_CC_Analytics integration file not found: ' . $integration_path );
		}
	}

	return $integrations;
}
add_filter( 'woocommerce_integrations', __NAMESPACE__ . '\\add_integration', 10 );

// Cron schedule every 1 hour
add_filter(
	'cron_schedules',
	function ( $schedules ) {
		if ( ! isset( $schedules['one_hours'] ) ) {
			$schedules['one_hours'] = array(
				'interval' => 3600,
				'display'  => __( 'Every 1 Hours' ),
			);
		}
		return $schedules;
	}
);

// Conditionally register block-based checkout consent integration
add_action(
	'woocommerce_blocks_checkout_block_registration',
	function ( $integration_registry ) {

		// Register SMS Consent Block
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-convertcart-sms-consent-block-integration.php';
		if ( class_exists( 'ConvertCart\\Analytics\\ConvertCart_SMS_Consent_Block_Integration' ) ) {
			$integration_registry->register( new \ConvertCart\Analytics\ConvertCart_SMS_Consent_Block_Integration() );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
				error_log( 'ConvertCart: SMS Consent Block registered successfully' );
			}
		}

		// Register Email Consent Block
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-convertcart-email-consent-block-integration.php';
		if ( class_exists( 'ConvertCart\\Analytics\\ConvertCart_Email_Consent_Block_Integration' ) ) {
			$integration_registry->register( new \ConvertCart\Analytics\ConvertCart_Email_Consent_Block_Integration() );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
				error_log( 'ConvertCart: Email Consent Block registered successfully' );
			}
		}
	}
);

// Conditionally register Store API schema fields for block checkout
add_action(
	'woocommerce_blocks_loaded',
	function () {
		if ( ! class_exists( '\Automattic\WooCommerce\StoreApi\StoreApi' ) ) {
			return;
		}
		
		if ( ! class_exists( '\Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema' ) || 
			 ! class_exists( '\Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema' ) ) {
			return;
		}

		$extend = \Automattic\WooCommerce\StoreApi\StoreApi::container()->get(
			\Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema::class
		);

		$extend->register_endpoint_data(
			array(
				'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema::IDENTIFIER,
				'namespace'       => 'convertcart',
				'schema_callback' => function () {
					return array(
						'sms_consent'   => array(
							'description' => __( 'Consent for SMS communications.', 'convertcart' ),
							'type'        => array( 'boolean', 'null' ),
						),
						'email_consent' => array(
							'description' => __( 'Consent for email communications.', 'convertcart' ),
							'type'        => array( 'boolean', 'null' ),
						),
					);
				},
			)
		);
	}
);

// Save Store API values to order meta
add_action(
	'woocommerce_store_api_checkout_update_order_from_request',
	function ( $order, $request ) {
		if ( isset( $request['extensions']['convertcart']['sms_consent'] ) ) {
			$consent = (bool) $request['extensions']['convertcart']['sms_consent'];
			if ( is_user_logged_in() && $consent ) {
				$user_id = get_current_user_id();
				update_user_meta( $user_id, 'sms_consent', $consent ? 'yes' : 'no' );
			} else {
				$order->update_meta_data( 'sms_consent', $consent ? 'yes' : 'no' );
			}
		}
		if ( isset( $request['extensions']['convertcart']['email_consent'] ) ) {
			$consent = (bool) $request['extensions']['convertcart']['email_consent'];
			if ( is_user_logged_in() && $consent ) {
				$user_id = get_current_user_id();
				update_user_meta( $user_id, 'email_consent', $consent ? 'yes' : 'no' );
			} else {
				$order->update_meta_data( 'email_consent', $consent ? 'yes' : 'no' );
			}
		}
	},
	10,
	2
);

// Add block-specific data attributes only when blocks are enabled
add_filter(
	'__experimental_woocommerce_blocks_add_data_attributes_to_block',
	function ( $blocks ) {
		// Only add data attributes if WooCommerce Blocks checkout is enabled
		$blocks[] = 'convertcart/sms-consent-block';
		$blocks[] = 'convertcart/email-consent-block';
		return $blocks;
	}
);
