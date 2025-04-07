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
	 * Constructor
	 *
	 * @param Integration $integration The integration instance.
	 */
	public function __construct($integration) {
		$this->consent_type = 'sms';
		$this->set_consent_properties();
		parent::__construct($integration);
		
		if ($this->is_enabled()) {
			$this->setup_hooks();
		}
	}

	/**
	 * Set SMS-specific properties.
	 */
	protected function set_consent_properties() {
		$this->enable_setting_key           = 'enable_sms_consent';
		$this->meta_key                     = 'sms_consent';
		$this->checkout_html_option_key     = 'cc_sms_consent_checkout_html';
		$this->registration_html_option_key = 'cc_sms_consent_registration_html';
		$this->account_html_option_key      = 'cc_sms_consent_account_html';
	}

	/**
	 * Setup SMS-specific hooks.
	 */
	protected function setup_child_hooks() {
		add_action('woocommerce_created_customer', array($this, 'update_consent_from_previous_orders'), 20);
	}

	/**
	 * Get the default HTML for the checkout page for SMS.
	 *
	 * @return string
	 */
	protected function get_default_checkout_html() {
		return '<p class="form-row form-row-wide">
			<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
				<input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="sms_consent" id="sms_consent" />
				<span>' . esc_html__('I consent to receive SMS communications', 'woocommerce_cc_analytics') . '</span>
			</label>
		</p>';
	}

	/**
	 * Get the default HTML for the registration page for SMS.
	 *
	 * @return string
	 */
	protected function get_default_registration_html() {
		return '<p class="form-row form-row-wide">
			<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
				<input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="sms_consent" id="sms_consent" />
				<span>' . esc_html__('I consent to receive SMS communications', 'woocommerce_cc_analytics') . '</span>
			</label>
		</p>';
	}

	/**
	 * Get the default HTML for the account page for SMS.
	 *
	 * @return string
	 */
	protected function get_default_account_html() {
		return '<div class="sms-consent-checkbox">
			<label for="sms_consent">
				<input type="checkbox" name="sms_consent" id="sms_consent" />
				<span>' . esc_html__( 'I consent to receive SMS communications', 'woocommerce_cc_analytics' ) . '</span>
			</label>
		</div>';
	}

	/**
	 * Updates SMS consent for a newly registered user based on their previous guest orders.
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

		// Check if user already has consent set
		$current_consent = get_user_meta( $customer_id, $this->meta_key, true );
		if ( ! empty( $current_consent ) ) {
			return;
		}

		// HPOS compatible order query
		$orders = wc_get_orders(array(
			'billing_email' => $user->user_email,
			'limit'         => -1,
			'type'          => 'shop_order',
			'customer_id'   => 0,
			'status'        => array_keys(wc_get_order_statuses()),
		));

		if ( empty( $orders ) ) {
			return;
		}

		// Check orders chronologically
		foreach ( $orders as $order ) {
			$order_consent = $order->get_meta($this->meta_key, true);
			if ( ! empty( $order_consent ) ) {
				update_user_meta( $customer_id, $this->meta_key, $order_consent );
				break;
			}
		}
	}
}
