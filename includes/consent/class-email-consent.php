<?php
/**
 * Email Consent functionality for Convert Cart Analytics.
 * Extends Base_Consent for common logic.
 *
 * @package  ConvertCart\Analytics\Consent
 * @category Consent
 */

namespace ConvertCart\Analytics\Consent;

use ConvertCart\Analytics\Core\Integration;

defined( 'ABSPATH' ) || exit;

/**
 * Email_Consent Class.
 */
class Email_Consent extends Base_Consent {

	/**
	 * Set Email-specific properties.
	 */
	protected function set_consent_properties() {
		$this->consent_type                 = 'email';
		$this->enable_setting_key           = 'enable_email_consent';
		$this->meta_key                     = 'email_consent';
		$this->checkout_html_option_key     = 'cc_email_consent_checkout_html';
		$this->registration_html_option_key = 'cc_email_consent_registration_html';
		$this->account_html_option_key      = 'cc_email_consent_account_html';
	}

	/**
	 * Get the default HTML for the checkout page for Email.
	 *
	 * @return string
	 */
	protected function get_default_checkout_html() {
		return '<div class="email-consent-checkbox">
			<label for="email_consent">
				<input type="checkbox" name="email_consent" id="email_consent" />
				<span>' . esc_html__( 'I consent to receive email communications.', 'woocommerce_cc_analytics' ) . '</span>
			</label>
		</div>';
	}

	/**
	 * Get the default HTML for the registration page for Email.
	 *
	 * @return string
	 */
	protected function get_default_registration_html() {
		return '<div class="email-consent-checkbox">
			<label for="email_consent">
				<input type="checkbox" name="email_consent" id="email_consent" />
				<span>' . esc_html__( 'I consent to receive email communications', 'woocommerce_cc_analytics' ) . '</span>
			</label>
		</div>';
	}

	/**
	 * Get the default HTML for the account page for Email.
	 *
	 * @return string
	 */
	protected function get_default_account_html() {
		return '<div class="email-consent-checkbox">
			<label for="email_consent">
				<input type="checkbox" name="email_consent" id="email_consent" />
				<span>' . esc_html__( 'I consent to receive email communications', 'woocommerce_cc_analytics' ) . '</span>
			</label>
		</div>';
	}

	public function __construct(Integration $integration) {
		$this->consent_type = 'email'; // Set this before parent constructor
		parent::__construct($integration);
	}

	/**
	 * Setup hooks - Add Email-specific hooks.
	 */
	protected function setup_hooks() {
		parent::setup_hooks();

		// Add email-specific hooks
		add_action('woocommerce_created_customer', array($this, 'update_consent_from_previous_orders'), 20, 1);
	}

	/**
	 * Update consent from previous guest orders when a customer account is created.
	 *
	 * @param int $customer_id The newly created customer ID.
	 */
	public function update_consent_from_previous_orders($customer_id) {
		if (!$this->is_enabled()) {
			return;
		}

		$user = get_user_by('id', $customer_id);
		if (!$user) {
			return;
		}

		// Check if user already has consent set
		$current_consent = get_user_meta($customer_id, $this->meta_key, true);
		if (!empty($current_consent)) {
			return;
		}

		// Get guest orders associated with the user's email
		$order_query = new \Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableQuery(array(
			'billing_email' => $user->user_email,
			'limit'        => -1,
			'type'         => 'shop_order',
			'customer_id'  => 0,
			'status'       => array_keys(wc_get_order_statuses()),
		));
		$orders = $order_query->get_orders();

		if (empty($orders)) {
			return;
		}

		// Check orders chronologically
		foreach ($orders as $order) {
			$order_consent = $order instanceof \WC_Order ? $order->get_meta($this->meta_key, true) : '';
			if (!empty($order_consent)) {
				update_user_meta($customer_id, $this->meta_key, $order_consent);
				break;
			}
		}
	}
}
