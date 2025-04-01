<?php
/**
 * SMS Consent functionality for Convert Cart Analytics.
 * Extends Base_Consent for common logic.
 *
 * @package  ConvertCart\Analytics\Consent
 * @category Consent
 */

namespace ConvertCart\Analytics\Consent;

use ConvertCart\Analytics\Core\Integration;

defined( 'ABSPATH' ) || exit;

/**
 * SMS_Consent Class.
 */
class SMS_Consent extends Base_Consent {

	/**
	 * Set SMS-specific properties.
	 */
	protected function set_consent_properties() {
		$this->consent_type                 = 'sms';
		$this->enable_setting_key           = 'enable_sms_consent';
		$this->meta_key                     = 'sms_consent';
		$this->checkout_html_option_key     = 'cc_sms_consent_checkout_html';
		$this->registration_html_option_key = 'cc_sms_consent_registration_html';
		$this->account_html_option_key      = 'cc_sms_consent_account_html';
	}

	/**
	 * Setup hooks - Add SMS-specific hooks.
	 */
	protected function setup_hooks() {
		parent::setup_hooks();

		add_action( 'woocommerce_created_customer', array( $this, 'update_consent_from_previous_orders' ), 20, 1 );
	}

	/**
	 * Get the default HTML for the checkout page for SMS.
	 *
	 * @return string
	 */
	protected function get_default_checkout_html() {
		return sprintf(
			'<div class="%1$s-consent-checkbox"><label for="%1$s_consent"><input type="checkbox" name="%1$s_consent" id="%1$s_consent"> %2$s</label></div>',
			esc_attr( $this->consent_type ),
			esc_html__( 'I consent to receive SMS communications.', 'woocommerce_cc_analytics' )
		);
	}

	/**
	 * Get the default HTML for the registration page for SMS.
	 *
	 * @return string
	 */
	protected function get_default_registration_html() {
		return sprintf(
			'<p class="form-row form-row-wide"><label for="%1$s_consent">%2$s</label><input type="checkbox" name="%1$s_consent" id="%1$s_consent"></p>',
			esc_attr( $this->consent_type ),
			esc_html__( 'I consent to receive SMS communications', 'woocommerce_cc_analytics' )
		);
	}

	/**
	 * Get the default HTML for the account page for SMS.
	 *
	 * @return string
	 */
	protected function get_default_account_html() {
		// Same as registration for SMS.
		return $this->get_default_registration_html();
	}

	/**
	 * Updates SMS consent for a newly registered user based on their previous guest orders.
	 * This logic is specific to SMS consent in the original code.
	 *
	 * @param int $customer_id The customer ID.
	 */
	public function update_consent_from_previous_orders( $customer_id ) {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$user = get_user_by( 'id', $customer_id );
		if ( ! $user || ! $user->user_email ) {
			return;
		}

		// Check if user already has consent set (e.g., from registration form).
		// If yes, don't overwrite with potentially older guest order data.
		$current_consent = get_user_meta( $customer_id, $this->meta_key, true );
		if ( ! empty( $current_consent ) ) {
			return;
		}

		// Get guest orders associated with the user's email.
		$orders = wc_get_orders(
			array(
				'billing_email' => $user->user_email,
				'limit'         => -1, // Check all previous orders.
				'type'          => 'shop_order',
				'customer_id'   => 0, // Ensure they were guest orders.
				'status'        => array_keys( wc_get_order_statuses() ), // Check all statuses.
			)
		);

		if ( empty( $orders ) ) {
			return;
		}

		// Check orders chronologically (newest first by default with wc_get_orders).
		foreach ( $orders as $order ) {
			$order_consent = $order->get_meta( $this->meta_key, true );
			if ( ! empty( $order_consent ) ) {
				// Found consent info on a previous guest order. Update user meta.
				update_user_meta( $customer_id, $this->meta_key, $order_consent );
				// Found the most recent guest order with consent info, stop checking.
				break;
			}
		}
	}
}
