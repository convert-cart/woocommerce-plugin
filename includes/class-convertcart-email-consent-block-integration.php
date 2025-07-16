<?php
/**
 * Email Consent Block Integration
 *
 * @package ConvertCart\Analytics
 */

namespace ConvertCart\Analytics;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

class ConvertCart_Email_Consent_Block_Integration implements IntegrationInterface {

	public function get_name() {
		return 'convertcart-email-consent';
	}

	public function initialize() {
		$this->register_scripts();
		$this->register_block_type();
	}

	private function register_scripts() {
		// Check if we should register scripts (prevent classic checkout conflicts)
		if ( is_admin() || ! function_exists( 'is_checkout' ) ) {
			// In admin or when WooCommerce functions aren't available, register normally
		} elseif ( is_checkout() ) {
			// On checkout page, check if it's using blocks
			global $post;
			if ( ! $post || ! has_block( 'woocommerce/checkout', $post ) ) {
				// Classic checkout detected - don't register scripts
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
					error_log( 'ConvertCart: Classic checkout detected, skipping Email consent script registration' );
				}
				return;
			}
		}

		// Use the plugin URL constant for reliable URL construction
		$plugin_url = defined( 'CONVERTCART_PLUGIN_URL' ) ? CONVERTCART_PLUGIN_URL : plugins_url( '', dirname( __DIR__ ) . '/cc-analytics.php' );

		// Register frontend script
		wp_register_script(
			'convertcart-email-consent-block-frontend',
			$plugin_url . 'assets/dist/js/email_consent/email-consent-block-frontend.js',
			array( 'wc-blocks-checkout', 'wp-element', 'wp-i18n', 'wp-components', 'wc-settings' ),
			'1.0.0',
			true
		);

		// Register editor script
		wp_register_script(
			'convertcart-email-consent-block',
			$plugin_url . 'assets/dist/js/email_consent/email-consent-block.js',
			array( 'wc-blocks-checkout', 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-components', 'wp-block-editor' ),
			'1.0.0',
			true
		);
	}

	public function get_script_handles() {
		return array( 'convertcart-email-consent-block-frontend' );
	}

	public function get_editor_script_handles() {
		return array( 'convertcart-email-consent-block' );
	}

	public function get_script_data() {
		$options      = get_option( 'woocommerce_cc_analytics_settings', array() );
		$emailConsent = isset( $options['enable_email_consent'] ) && ( 'live' === $options['enable_email_consent'] ) ? true : false;
		$html         = get_option( 'cc_email_consent_checkout_html', '' );
		// Check if HTML is not empty then take out the text from it
		$text = ! empty( $html ) ? strip_tags( $html ) : __( 'I agree to receive marketing emails from this store.', 'convertcart' );
		// If the text is empty, use a default message
		if ( empty( $text ) ) {
			$text = __( 'I agree to receive marketing emails from this store.', 'convertcart' );
		}
		// Ensure the text is sanitized
		$text = sanitize_text_field( $text );
		// Return the data for the block
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
			error_log(
				'ConvertCart: Email consent block data prepared with text: ' . $text
				. ', trackingEnabled: ' . ( $emailConsent ? 'true' : 'false' )
			);
		}
		if ( is_user_logged_in() ) {
			// Here we only need the consent data from user meta
			$user_email_consent = get_user_meta( get_current_user_id(), 'email_consent', true );
		} else {
			$user_email_consent = false; // Default to false for non-logged-in users
		}
		// Return the data array
		return array(
			'defaultText'     => __( $text, 'convertcart' ),
			'trackingEnabled' => $emailConsent,
			'consent'         => $user_email_consent === 'yes' ? true : false,
		);
	}

	private function register_block_type() {
		if ( function_exists( 'register_block_type_from_metadata' ) ) {
			$block_path = defined( 'CONVERTCART_PLUGIN_PATH' ) ? CONVERTCART_PLUGIN_PATH . 'assets/dist/js/email_consent' : dirname( __DIR__ ) . '/assets/dist/js/email_consent';
			register_block_type_from_metadata(
				$block_path,
				array(
					'view_script_handles'   => array( 'convertcart-email-consent-block-frontend' ),
					'editor_script_handles' => array( 'convertcart-email-consent-block' ),
				)
			);
		}
	}
}
