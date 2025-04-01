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
		return sprintf(
			'<div class="%1$s-consent-checkbox"><label for="%1$s_consent"><input type="checkbox" name="%1$s_consent" id="%1$s_consent"> %2$s</label></div>',
			esc_attr( $this->consent_type ),
			esc_html__( 'I consent to receive email communications.', 'woocommerce_cc_analytics' )
		);
	}

	/**
	 * Get the default HTML for the registration page for Email.
	 *
	 * @return string
	 */
	protected function get_default_registration_html() {
		return sprintf(
			'<p class="form-row form-row-wide"><label for="%1$s_consent">%2$s</label><input type="checkbox" name="%1$s_consent" id="%1$s_consent"></p>',
			esc_attr( $this->consent_type ),
			esc_html__( 'I consent to receive email communications', 'woocommerce_cc_analytics' )
		);
	}

	/**
	 * Get the default HTML for the account page for Email.
	 *
	 * @return string
	 */
	protected function get_default_account_html() {
		// Same as registration for Email.
		return $this->get_default_registration_html();
	}

	// No email-specific methods like update_consent_from_previous_orders were present,
	// so no need to add extra methods here unless required.
}
