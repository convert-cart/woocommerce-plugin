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
	 */
	public function __construct($integration) {
		// Set consent type and properties BEFORE calling parent constructor
		$this->consent_type = 'sms';
		$this->set_consent_properties();
		
		// Now call parent constructor which will validate properties
		parent::__construct($integration);
		
		// Add hooks after successful initialization
		if ($this->is_enabled()) {
			// Hook for updating consent from previous orders - runs after registration save
			add_action('woocommerce_created_customer', array($this, 'update_consent_from_previous_orders'), 20, 1);
		}
	}

	/**
	 * Set SMS-specific properties.
	 */
	protected function set_consent_properties() {
		// Don't set consent_type here anymore, it's set in constructor
		$this->enable_setting_key           = 'enable_sms_consent';
		$this->meta_key                     = 'sms_consent';
		$this->checkout_html_option_key     = 'cc_sms_consent_checkout_html';
		$this->registration_html_option_key = 'cc_sms_consent_registration_html';
		$this->account_html_option_key      = 'cc_sms_consent_account_html';
	}

	/**
	 * Setup hooks - This method is now primarily for *SMS-specific* hooks.
	 * The base class handles common checkout, registration, and account hooks.
	 */
	protected function setup_child_hooks() {
		parent::setup_child_hooks(); // Good practice to call parent
		$this->log_debug('Running setup_child_hooks for SMS.');
		// Add any SMS-specific hooks here if needed in the future.
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
				<span>' . esc_html__( 'I consent to receive SMS communications My Account.', 'woocommerce_cc_analytics' ) . '</span>
			</label>
		</div>';
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
		$order_query = new \Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableQuery(array(
			'billing_email' => $user->user_email,
			'limit'         => -1,
			'type'          => 'shop_order',
			'customer_id'   => 0,
			'status'        => array_keys(wc_get_order_statuses()),
		));
		$orders = $order_query->get_orders();

		if ( empty( $orders ) ) {
			return;
		}

		// Check orders chronologically (newest first by default)
		foreach ( $orders as $order ) {
			$order_consent = $order instanceof \WC_Order ? $order->get_meta($this->meta_key, true) : '';
			if ( ! empty( $order_consent ) ) {
				// Found consent info on a previous guest order. Update user meta.
				update_user_meta( $customer_id, $this->meta_key, $order_consent );
				break;
			}
		}
	}
}
