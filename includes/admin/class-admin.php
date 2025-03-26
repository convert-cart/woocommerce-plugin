<?php
/**
 * Admin functionality for Convert Cart Analytics.
 *
 * @package  ConvertCart\Analytics\Admin
 * @category Admin
 */

namespace ConvertCart\Analytics\Admin;

use ConvertCart\Analytics\Core\Integration;

class Admin {

	/**
	 * Integration instance.
	 *
	 * @var Integration
	 */
	private $integration;

	/**
	 * Constructor.
	 *
	 * @param Integration $integration Integration instance.
	 */
	public function __construct( $integration ) {
		$this->integration = $integration;
		$this->setup_hooks();
	}

	/**
	 * Setup hooks.
	 */
	private function setup_hooks() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
	}

	/**
	 * Add admin menu items.
	 */
	public function add_admin_menu() {
		// Only show the menu if the integration is enabled.
		if ( isset( $this->integration->settings ) && 'yes' === $this->integration->settings['enabled'] ) {
			add_submenu_page(
				'woocommerce',
				__( 'Convert Cart SMS Consent', 'woocommerce_cc_analytics' ),
				__( 'CC SMS Consent', 'woocommerce_cc_analytics' ),
				'manage_options',
				'convert-cart-sms-consent',
				array( $this, 'render_convert_cart_settings_page' )
			);

			// Only show the email consent menu if email consent is enabled.
			if ( isset( $this->integration->settings['enable_email_consent'] ) && 'live' === $this->integration->settings['enable_email_consent'] ) {
				add_submenu_page(
					'woocommerce',
					__( 'Convert Cart Email Consent', 'woocommerce_cc_analytics' ),
					__( 'CC Email Consent', 'woocommerce_cc_analytics' ),
					'manage_options',
					'convert-cart-email-consent',
					array( $this, 'render_convert_cart_settings_page' )
				);
			}
		}
	}

	/**
	 * Renders the Convert Cart settings page.
	 *
	 * @return void
	 */
	public function render_convert_cart_settings_page() {
		$options = get_option( 'woocommerce_cc_analytics_settings' );

		// Check if we're on the SMS consent page.
		if ( isset( $_GET['page'] ) && 'convert-cart-sms-consent' === $_GET['page'] ) {
			// Check if form is submitted.
			if ( isset( $_POST['save_convert_cart_sms_html'] ) && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'save_convert_cart_sms_html' ) ) {
				// PHP Validation to ensure the sms_consent checkbox is present.
				$cc_sms_consent_checkout_html     = isset( $_POST['cc_sms_consent_checkout_html'] ) ? sanitize_text_field( wp_unslash( $_POST['cc_sms_consent_checkout_html'] ) ) : '';
				$cc_sms_consent_registration_html = isset( $_POST['cc_sms_consent_registration_html'] ) ? sanitize_text_field( wp_unslash( $_POST['cc_sms_consent_registration_html'] ) ) : '';
				$cc_sms_consent_account_html      = isset( $_POST['cc_sms_consent_account_html'] ) ? sanitize_text_field( wp_unslash( $_POST['cc_sms_consent_account_html'] ) ) : '';

				if (
					strpos( $cc_sms_consent_checkout_html, 'sms_consent' ) !== false &&
					strpos( $cc_sms_consent_registration_html, 'sms_consent' ) !== false &&
					strpos( $cc_sms_consent_account_html, 'sms_consent' ) !== false
				) {
					// Save the HTML.
					update_option( 'cc_sms_consent_checkout_html', $cc_sms_consent_checkout_html );
					update_option( 'cc_sms_consent_registration_html', $cc_sms_consent_registration_html );
					update_option( 'cc_sms_consent_account_html', $cc_sms_consent_account_html );

					// Show success message.
					add_settings_error( 'convert_cart_sms_messages', 'convert_cart_sms_message', __( 'SMS consent HTML saved successfully.', 'woocommerce_cc_analytics' ), 'updated' );
				} else {
					// Show error message.
					add_settings_error( 'convert_cart_sms_messages', 'convert_cart_sms_error', __( 'SMS consent HTML must contain the sms_consent checkbox.', 'woocommerce_cc_analytics' ), 'error' );
				}
			}

			// Display settings errors.
			settings_errors( 'convert_cart_sms_messages' );

			// Get the HTML.
			$default_checkout_html     = '<div class="sms-consent-checkbox"><label for="sms_consent"><input type="checkbox" name="sms_consent" id="sms_consent"> I consent to receive SMS communications.</label></div>';
			$default_registration_html = '<p class="form-row form-row-wide"><label for="sms_consent">' . esc_html__( 'I consent to receive SMS communications', 'woocommerce' ) . '</label><input type="checkbox" name="sms_consent" id="sms_consent"></p>';
			$default_account_html      = '<p class="form-row form-row-wide"><label for="sms_consent">' . esc_html__( 'I consent to receive SMS communications', 'woocommerce' ) . '</label><input type="checkbox" name="sms_consent" id="sms_consent"></p>';

			$checkout_html     = get_option( 'cc_sms_consent_checkout_html', $default_checkout_html );
			$registration_html = get_option( 'cc_sms_consent_registration_html', $default_registration_html );
			$account_html      = get_option( 'cc_sms_consent_account_html', $default_account_html );

			include_once plugin_dir_path( __FILE__ ) . 'views/sms-settings-page.php';
		} elseif ( isset( $_GET['page'] ) && 'convert-cart-email-consent' === $_GET['page'] ) {
			// Check if form is submitted.
			if ( isset( $_POST['save_convert_cart_email_html'] ) && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'save_convert_cart_email_html' ) ) {
				// PHP Validation to ensure the email_consent checkbox is present.
				$cc_email_consent_checkout_html     = isset( $_POST['cc_email_consent_checkout_html'] ) ? sanitize_text_field( wp_unslash( $_POST['cc_email_consent_checkout_html'] ) ) : '';
				$cc_email_consent_registration_html = isset( $_POST['cc_email_consent_registration_html'] ) ? sanitize_text_field( wp_unslash( $_POST['cc_email_consent_registration_html'] ) ) : '';
				$cc_email_consent_account_html      = isset( $_POST['cc_email_consent_account_html'] ) ? sanitize_text_field( wp_unslash( $_POST['cc_email_consent_account_html'] ) ) : '';

				if (
					strpos( $cc_email_consent_checkout_html, 'email_consent' ) !== false &&
					strpos( $cc_email_consent_registration_html, 'email_consent' ) !== false &&
					strpos( $cc_email_consent_account_html, 'email_consent' ) !== false
				) {
					// Save the HTML.
					update_option( 'cc_email_consent_checkout_html', $cc_email_consent_checkout_html );
					update_option( 'cc_email_consent_registration_html', $cc_email_consent_registration_html );
					update_option( 'cc_email_consent_account_html', $cc_email_consent_account_html );

					// Show success message.
					add_settings_error( 'convert_cart_email_messages', 'convert_cart_email_message', __( 'Email consent HTML saved successfully.', 'woocommerce_cc_analytics' ), 'updated' );
				} else {
					// Show error message.
					add_settings_error( 'convert_cart_email_messages', 'convert_cart_email_error', __( 'Email consent HTML must contain the email_consent checkbox.', 'woocommerce_cc_analytics' ), 'error' );
				}
			}

			// Display settings errors.
			settings_errors( 'convert_cart_email_messages' );

			// Get the HTML.
			$default_checkout_html     = '<div class="email-consent-checkbox"><label for="email_consent"><input type="checkbox" name="email_consent" id="email_consent"> I consent to receive email communications.</label></div>';
			$default_registration_html = '<p class="form-row form-row-wide"><label for="email_consent">' . esc_html__( 'I consent to receive email communications', 'woocommerce' ) . '</label><input type="checkbox" name="email_consent" id="email_consent"></p>';
			$default_account_html      = '<p class="form-row form-row-wide"><label for="email_consent">' . esc_html__( 'I consent to receive email communications', 'woocommerce' ) . '</label><input type="checkbox" name="email_consent" id="email_consent"></p>';

			$checkout_html     = get_option( 'cc_email_consent_checkout_html', $default_checkout_html );
			$registration_html = get_option( 'cc_email_consent_registration_html', $default_registration_html );
			$account_html      = get_option( 'cc_email_consent_account_html', $default_account_html );

			include_once plugin_dir_path( __FILE__ ) . 'views/email-settings-page.php';
		}
	}
}
