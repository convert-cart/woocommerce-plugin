<?php
/**
 * Plugin Name: Convert Cart Analytics
 * Description: Official Convert Cart Analytics WordPress plugin that tracks user behavior, transforms abandoned carts into product pages, and optimizes your store for conversions.
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
 */

namespace ConvertCart\Analytics;

defined( 'ABSPATH' ) || exit;

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
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			__FILE__,
			true
		);
	}
} );

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
add_filter( 'cron_schedules', function ( $schedules ) {
	if ( ! isset( $schedules['one_hours'] ) ) {
		$schedules['one_hours'] = [
			'interval' => 3600,
			'display'  => __( 'Every 1 Hours' ),
		];
	}
	return $schedules;
} );

// Conditionally register block-based checkout consent integration
add_action( 'woocommerce_blocks_checkout_block_registration', function ( $integration_registry ) {
	// Only register if WooCommerce Blocks checkout is enabled and active
	if ( class_exists( '\Automattic\WooCommerce\Blocks\Features\FeaturesController' ) ) {
		$features = \Automattic\WooCommerce\Blocks\Package::container()->get(
			\Automattic\WooCommerce\Blocks\Features\FeaturesController::class
		);

		// Check if checkout feature is enabled and not using classic checkout
		if ( ! $features->is_feature_enabled( 'checkout' ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
				error_log( 'ConvertCart: WooCommerce Blocks checkout is not enabled, skipping consent block registration' );
			}
			return; // Exit early if blocks checkout is not enabled
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
			error_log( 'ConvertCart: WooCommerce Blocks checkout is enabled, registering consent blocks' );
		}
	} else {
		// If FeaturesController doesn't exist, don't register blocks
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
			error_log( 'ConvertCart: WooCommerce Blocks FeaturesController not found, skipping consent block registration' );
		}
		return;
	}
	
    // Register SMS Consent Block
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-sms-block-integration.php';
    if ( class_exists( 'ConvertCart\\Analytics\\ConvertCart_SMS_Consent_Block_Integration' ) ) {
        $integration_registry->register( new ConvertCart_SMS_Consent_Block_Integration() );
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
            error_log( 'ConvertCart: SMS Consent Block registered successfully' );
        }
    }

    // Register Email Consent Block
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-email-block-integration.php';
    if ( class_exists( 'ConvertCart\\Analytics\\ConvertCart_Email_Consent_Block_Integration' ) ) {
        $integration_registry->register( new ConvertCart_Email_Consent_Block_Integration() );
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
            error_log( 'ConvertCart: Email Consent Block registered successfully' );
        }
    }
} );

// Conditionally register Store API schema fields for block checkout
add_action( 'woocommerce_blocks_loaded', function () {
	if (
		class_exists( '\Automattic\WooCommerce\Blocks\Features\FeaturesController' ) &&
		class_exists( '\Automattic\WooCommerce\StoreApi\StoreApi' )
	) {
		$features = \Automattic\WooCommerce\Blocks\Package::container()->get(
			\Automattic\WooCommerce\Blocks\Features\FeaturesController::class
		);

		// Only register Store API extensions if checkout feature is enabled
		if ( ! $features->is_feature_enabled( 'checkout' ) ) {
			return; // Exit early if blocks checkout is not enabled
		}

		$extend = \Automattic\WooCommerce\StoreApi\StoreApi::container()->get(
			\Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema::class
		);

		$extend->register_endpoint_data( [
			'endpoint'  => \Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema::IDENTIFIER,
			'namespace' => 'convertcart',
			'schema_callback' => function () {
				return [
					'sms_consent' => [
						'description' => __( 'Consent for SMS communications.', 'convertcart' ),
						'type'        => [ 'boolean', 'null' ],
					],
					'email_consent' => [
						'description' => __( 'Consent for email communications.', 'convertcart' ),
						'type'        => [ 'boolean', 'null' ],
					],
				];
			},
		] );
	}
} );

// Save Store API values to order meta
add_action( 'woocommerce_store_api_checkout_update_order_from_request', function ( $order, $request ) {
	if ( isset( $request['extensions']['convertcart']['sms_consent'] ) ) {
		$consent = (bool) $request['extensions']['convertcart']['sms_consent'];
		$order->update_meta_data( 'convertcart_sms_consent', $consent ? 'yes' : 'no' );
	}
	if ( isset( $request['extensions']['convertcart']['email_consent'] ) ) {
		$consent = (bool) $request['extensions']['convertcart']['email_consent'];
		$order->update_meta_data( 'convertcart_email_consent', $consent ? 'yes' : 'no' );
	}
}, 10, 2 );

// Add block-specific data attributes only when blocks are enabled
add_filter( '__experimental_woocommerce_blocks_add_data_attributes_to_block', function ( $blocks ) {
	// Only add data attributes if WooCommerce Blocks checkout is enabled
	if ( class_exists( '\Automattic\WooCommerce\Blocks\Features\FeaturesController' ) ) {
		$features = \Automattic\WooCommerce\Blocks\Package::container()->get(
			\Automattic\WooCommerce\Blocks\Features\FeaturesController::class
		);

		if ( $features->is_feature_enabled( 'checkout' ) ) {
			$blocks[] = 'convertcart/sms-consent-block';
			$blocks[] = 'convertcart/email-consent-block';
		}
	}
	
	return $blocks;
} );

// Prevent loading blocks scripts on classic checkout pages
add_action( 'wp_enqueue_scripts', function() {
	// Only on checkout page
	if ( ! is_checkout() ) {
		return;
	}
	
	// Check if this checkout page is using classic checkout
	global $post;
	if ( $post && has_block( 'woocommerce/checkout', $post ) ) {
		// This page uses WooCommerce Blocks checkout - allow our scripts
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
			error_log( 'ConvertCart: Checkout page uses WooCommerce Blocks - consent blocks will be available' );
		}
	} else {
		// This page uses classic checkout - dequeue our scripts to prevent errors
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
			error_log( 'ConvertCart: Checkout page uses classic checkout - dequeuing consent block scripts' );
		}
		
		// Dequeue our scripts to prevent JavaScript errors
		wp_dequeue_script( 'convertcart-sms-consent-block-frontend' );
		wp_dequeue_script( 'convertcart-email-consent-block-frontend' );
		wp_deregister_script( 'convertcart-sms-consent-block-frontend' );
		wp_deregister_script( 'convertcart-email-consent-block-frontend' );
	}
}, 999 ); // High priority to run after scripts are registered
