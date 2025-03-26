<?php
/**
 * Email Consent functionality for Convert Cart Analytics.
 *
 * @package  ConvertCart\Analytics\Consent
 * @category Consent
 */

namespace ConvertCart\Analytics\Consent;

use ConvertCart\Analytics\Core\Integration;

class Email_Consent {

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
		add_action( 'woocommerce_review_order_before_submit', array( $this, 'add_email_consent_checkbox' ) );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_email_consent_to_order_or_customer' ), 10, 1 );
		add_action( 'woocommerce_created_customer', array( $this, 'save_email_consent_when_account_is_created' ), 10, 1 );
		add_action( 'woocommerce_edit_account_form', array( $this, 'add_email_consent_checkbox_to_account_page' ) );
		add_action( 'woocommerce_save_account_details', array( $this, 'save_email_consent_from_account_page' ), 12, 1 );
		add_action( 'woocommerce_register_form', array( $this, 'add_email_consent_to_registration_form' ) );
		add_action( 'woocommerce_created_customer', array( $this, 'save_email_consent_from_registration_form' ), 10, 1 );
	}

	/**
	 * Adds email consent checkbox to checkout page.
	 *
	 * @return void
	 */
	public function add_email_consent_checkbox() {
		$options = get_option( 'woocommerce_cc_analytics_settings' );
		if ( isset( $options['enable_email_consent'] ) && ( 'live' === $options['enable_email_consent'] ) ) {
			// Default HTML as a string.
			$default_html = '<div class="email-consent-checkbox"><label for="email_consent"><input type="checkbox" name="email_consent" id="email_consent"> I consent to receive email communications.</label></div>';

			// Get custom HTML or use default.
			$checkout_html = get_option( 'cc_email_consent_checkout_html', $default_html );

			echo wp_kses_post( $checkout_html );
		}
	}

	/**
	 * Saves email consent to order or customer.
	 *
	 * @param \WC_Order $order The order object.
	 * @return void
	 */
	public function save_email_consent_to_order_or_customer( $order ) {
		$options = get_option( 'woocommerce_cc_analytics_settings' );
		if ( isset( $options['enable_email_consent'] ) && ( 'live' === $options['enable_email_consent'] ) ) {
			if ( isset( $_POST['email_consent'] ) && wp_verify_nonce( isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '', 'woocommerce-process_checkout' ) ) {
				$order->update_meta_data( 'email_consent', 'yes' );

				// If the user is logged in, also save to user meta.
				$user_id = $order->get_user_id();
				if ( $user_id > 0 ) {
					update_user_meta( $user_id, 'email_consent', 'yes' );
				}
			} else {
				$order->update_meta_data( 'email_consent', 'no' );
			}
		}
	}

	/**
	 * Saves email consent when account is created.
	 *
	 * @param int $customer_id        The customer ID.
	 * @return void
	 */
	public function save_email_consent_when_account_is_created( $customer_id ) {
		$options = get_option( 'woocommerce_cc_analytics_settings' );
		if ( isset( $options['enable_email_consent'] ) && ( 'live' === $options['enable_email_consent'] ) ) {
			if ( isset( $_POST['email_consent'] ) && wp_verify_nonce( isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '', 'woocommerce-register' ) ) {
				update_user_meta( $customer_id, 'email_consent', 'yes' );
			} else {
				update_user_meta( $customer_id, 'email_consent', 'no' );
			}
		}
	}

	/**
	 * Adds email consent checkbox to account page.
	 *
	 * @return void
	 */
	public function add_email_consent_checkbox_to_account_page() {
		$options = get_option( 'woocommerce_cc_analytics_settings' );
		if ( isset( $options['enable_email_consent'] ) && ( 'live' === $options['enable_email_consent'] ) ) {
			$user_id       = get_current_user_id();
			$email_consent = get_user_meta( $user_id, 'email_consent', true );

			// Default HTML as a string.
			$default_html = '<p class="form-row form-row-wide"><label for="email_consent">' . esc_html__( 'I consent to receive email communications', 'woocommerce' ) . '</label><input type="checkbox" name="email_consent" id="email_consent"></p>';

			// Get custom HTML or use default.
			$account_html = get_option( 'cc_email_consent_account_html', $default_html );

			$account_html = str_replace( 'id="email_consent"', 'id="email_consent" ' . checked( $email_consent, 'yes', false ), $account_html );

			echo wp_kses_post( $account_html );
		}
	}

	/**
	 * Saves email consent from account page.
	 *
	 * @param int $user_id The user ID.
	 * @return void
	 */
	public function save_email_consent_from_account_page( $user_id ) {
		$options = get_option( 'woocommerce_cc_analytics_settings' );
		if ( 'live' === $options['enable_email_consent'] && isset( $options['enable_email_consent'] ) ) {
			if ( isset( $_POST['email_consent'] ) && wp_verify_nonce( isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '', 'save_account_details' ) ) {
				update_user_meta( $user_id, 'email_consent', 'yes' );
			} else {
				update_user_meta( $user_id, 'email_consent', 'no' );
			}
		}
	}

	/**
	 * Adds email consent checkbox to registration form.
	 *
	 * @return void
	 */
	public function add_email_consent_to_registration_form() {
		$options = get_option( 'woocommerce_cc_analytics_settings' );
		if ( 'live' === $options['enable_email_consent'] && isset( $options['enable_email_consent'] ) ) {
			// Default HTML as a string.
			$default_html = '
<p class="form-row form-row-wide">
	<label for="email_consent">' . esc_html__( 'I consent to receive email communications', 'woocommerce' ) . '</label>
	<input type="checkbox" name="email_consent" id="email_consent" />
</p>';

			// Get custom HTML or use default.
			$registration_html = get_option( 'cc_email_consent_registration_html', $default_html );

			echo wp_kses_post( $registration_html );
		}
	}

	/**
	 * Saves email consent from registration form.
	 *
	 * @param int $customer_id The customer ID.
	 * @return void
	 */
	public function save_email_consent_from_registration_form( $customer_id ) {
		$options = get_option( 'woocommerce_cc_analytics_settings' );
		if ( 'live' === $options['enable_email_consent'] && isset( $options['enable_email_consent'] ) ) {
			if ( isset( $_POST['email_consent'] ) && wp_verify_nonce( isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '', 'woocommerce-register' ) ) {
				update_user_meta( $customer_id, 'email_consent', 'yes' );
			} else {
				update_user_meta( $customer_id, 'email_consent', 'no' );
			}
		}
	}
}
