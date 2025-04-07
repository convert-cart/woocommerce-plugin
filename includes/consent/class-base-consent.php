<?php
/**
 * Abstract Base Class for Consent functionality.
 *
 * @package  ConvertCart\Analytics\Consent
 * @category Consent
 */

namespace ConvertCart\Analytics\Consent;

use ConvertCart\Analytics\Core\Integration;

defined( 'ABSPATH' ) || exit;

/**
 * Abstract Base_Consent Class.
 */
abstract class Base_Consent {

	/**
	 * Integration instance.
	 *
	 * @var Integration
	 */
	protected $integration;

	/**
	 * The type of consent (e.g., 'sms', 'email').
	 * Should be defined in the child class.
	 *
	 * @var string
	 */
	protected $consent_type = '';

	/**
	 * The main settings option key.
	 *
	 * @var string
	 */
	protected $settings_option_key = 'woocommerce_cc_analytics_settings';

	/**
	 * The key within the settings array that enables this consent type.
	 * Should be defined in the child class.
	 *
	 * @var string
	 */
	protected $enable_setting_key = '';

	/**
	 * The meta key used to store consent in order and user meta.
	 * Should be defined in the child class.
	 *
	 * @var string
	 */
	protected $meta_key = '';

	/**
	 * The option key for the checkout page HTML snippet.
	 * Should be defined in the child class.
	 *
	 * @var string
	 */
	protected $checkout_html_option_key = '';

	/**
	 * The option key for the registration page HTML snippet.
	 * Should be defined in the child class.
	 *
	 * @var string
	 */
	protected $registration_html_option_key = '';

	/**
	 * The option key for the account page HTML snippet.
	 * Should be defined in the child class.
	 *
	 * @var string
	 */
	protected $account_html_option_key = '';

	/**
	 * The nonce action for the checkout form.
	 *
	 * @var string
	 */
	protected $checkout_nonce_action = 'woocommerce-process_checkout';

	/**
	 * The nonce action for the registration form.
	 *
	 * @var string
	 */
	protected $register_nonce_action = 'woocommerce-register';

	/**
	 * The nonce action for the save account details form.
	 *
	 * @var string
	 */
	protected $account_details_nonce_action = 'save_account_details';


	/**
	 * Constructor with duplicate prevention
	 */
	public function __construct(Integration $integration) {
		// Set consent type first in child classes
		if (empty($this->consent_type)) {
			throw new \Exception('Consent type must be set before calling parent constructor');
		}
		
		$this->integration = $integration;
		$this->set_consent_properties();
		$this->validate_properties();
		
		// Setup hooks only if enabled
		if ($this->is_enabled()) {
		$this->setup_hooks();
		}
	}

	/**
	 * Abstract method to force child classes to define their specific properties.
	 */
	abstract protected function set_consent_properties();

	/**
	 * Validates that essential properties are set by the child class.
	 *
	 * @throws \Exception If a required property is not set.
	 */
	private function validate_properties() {
		$required_properties = array(
			'consent_type',
			'enable_setting_key',
			'meta_key',
			'checkout_html_option_key',
			'registration_html_option_key',
			'account_html_option_key',
		);
		foreach ( $required_properties as $prop ) {
			if ( empty( $this->$prop ) ) {
				throw new \Exception( sprintf( 'Required property "%s" must be set in class %s.', $prop, get_called_class() ) );
			}
		}
	}

	/**
	 * Get the main plugin settings.
	 *
	 * @return array
	 */
	protected function get_settings() {
		return get_option( $this->settings_option_key, array() );
	}

	/**
	 * Check if this consent type is enabled in settings.
	 *
	 * @return bool
	 */
	protected function is_enabled() {
		$options = get_option($this->settings_option_key);
		$enabled = isset($options[$this->enable_setting_key]) && 
				   ($options[$this->enable_setting_key] === 'live' || 
				    $options[$this->enable_setting_key] === 'draft');
		
		$this->log_debug($this->consent_type . ' consent is ' . ($enabled ? 'enabled' : 'disabled'));
		return $enabled;
	}

	/**
	 * Get the default HTML for the checkout page.
	 * Abstract method, must be implemented by child classes.
	 *
	 * @return string
	 */
	abstract protected function get_default_checkout_html();

	/**
	 * Get the default HTML for the registration page.
	 * Abstract method, must be implemented by child classes.
	 *
	 * @return string
	 */
	abstract protected function get_default_registration_html();

	/**
	 * Get the default HTML for the account page.
	 * Abstract method, must be implemented by child classes.
	 *
	 * @return string
	 */
	abstract protected function get_default_account_html();

	/**
	 * Setup common hooks.
	 */
	protected function setup_hooks() {
		if (!$this->is_enabled()) {
			return;
		}

		// Account page hooks
		add_action('woocommerce_edit_account_form', array($this, 'add_consent_checkbox_to_account'), 15);
		add_action('woocommerce_save_account_details', array($this, 'save_consent_from_account'), 10);

		// Registration page hooks
		add_action('woocommerce_register_form', array($this, 'add_consent_checkbox_to_registration'), 15);
		add_action('woocommerce_created_customer', array($this, 'save_consent_from_registration'), 10);

		// Checkout page hooks
		add_action('woocommerce_checkout_before_terms_and_conditions', array($this, 'add_consent_checkbox_to_checkout'), 15);
		add_action('woocommerce_checkout_create_order', array($this, 'save_consent_from_checkout'), 10);

		$this->log_debug('Hooks setup complete for ' . $this->consent_type . ' consent');
	}

	/**
	 * Validates and ensures required checkbox exists in HTML.
	 */
	protected function validate_consent_html($html, $input_name) {
		if (empty($html)) {
			return $this->get_default_account_html();
		}

		// Check if checkbox input exists with correct attributes
		$has_checkbox = strpos($html, '<input type="checkbox"') !== false &&
					   strpos($html, 'name="' . $input_name . '"') !== false &&
					   strpos($html, 'id="' . $input_name . '"') !== false;

		if (!$has_checkbox) {
			// If checkbox is missing, insert it before the span tag
			if (strpos($html, '<span>') !== false) {
				$html = str_replace(
					'<span>',
					'<input type="checkbox" name="' . $input_name . '" id="' . $input_name . '" /><span>',
					$html
				);
			} else {
				// If no suitable place to insert, return default
				return $this->get_default_account_html();
			}
		}

		return $html;
	}

	/**
	 * Debug logging helper
	 */
	protected function log_debug($message) {
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('Convert Cart Consent Debug: [' . $this->consent_type . '] ' . $message);
		}
	}

	/**
	 * Adds consent checkbox to checkout page.
	 */
	public function add_consent_checkbox_to_checkout() {
		if ( ! $this->is_enabled() ) {
			return;
		}
		
		$default_html = $this->get_default_checkout_html();
		$checkout_html = get_option( $this->checkout_html_option_key, $default_html );
		
		// Validate and ensure checkbox exists
		$checkout_html = $this->validate_consent_html($checkout_html, $this->meta_key);
		
		if ( is_user_logged_in() ) {
			$user_id = get_current_user_id();
			$consent_value = get_user_meta( $user_id, $this->meta_key, true );
			if ( $consent_value === 'yes' ) {
				$checkout_html = str_replace( 
					'type="checkbox"', 
					'type="checkbox" checked="checked"', 
					$checkout_html 
				);
			}
		}
		
		echo wp_kses_post( $checkout_html );
	}

	/**
	 * Saves consent from checkout.
	 *
	 * @param \WC_Order $order The order object.
	 */
	public function save_consent_from_checkout($order) {
		if (!$this->is_enabled()) {
			return;
		}

		$consent_value = isset($_POST[$this->meta_key]) ? 'yes' : 'no';

		if (is_user_logged_in()) {
			// For logged-in users, save to user meta
			$user_id = get_current_user_id();
			update_user_meta($user_id, $this->meta_key, $consent_value);
			update_user_meta($user_id, $this->meta_key . '_updated', current_time('mysql'));
		}

		// Always save to order meta
		$order->update_meta_data($this->meta_key, $consent_value);
		$order->update_meta_data($this->meta_key . '_updated', current_time('mysql'));
		
		$this->log_debug(sprintf(
			'Saved %s consent from checkout. Order: %d, Value: %s',
			$this->consent_type,
			$order->get_id(),
			$consent_value
		));
	}

	/**
	 * Saves consent when an account is created during checkout.
	 * Uses the value stored in the session by save_consent_from_checkout.
	 *
	 * @param int $customer_id The new customer ID.
	 */
	public function save_consent_on_account_creation_from_checkout( $customer_id ) {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$session_key = 'cc_pending_' . $this->meta_key;
		if ( WC()->session && WC()->session->get( $session_key ) ) {
			$consent_value = WC()->session->get( $session_key );
			update_user_meta( $customer_id, $this->meta_key, $consent_value );
			WC()->session->__unset( $session_key ); // Clean up session data.
		}
		// If not set in session, it implies either account wasn't created during this checkout,
		// or consent wasn't given/processed correctly. We avoid setting 'no' here
		// to prevent overwriting consent possibly set via registration form if hooks fire closely.
	}


	/**
	 * Adds consent checkbox to the registration form.
	 */
	public function add_consent_checkbox_to_registration() {
		if ( ! $this->is_enabled() ) {
			return;
		}
		
		$default_html = $this->get_default_registration_html();
		$registration_html = get_option( $this->registration_html_option_key, $default_html );
		
		// Validate and ensure checkbox exists
		$registration_html = $this->validate_consent_html($registration_html, $this->meta_key);
		
		echo wp_kses_post( $registration_html );
	}

	/**
	 * Saves consent from the registration form.
	 *
	 * @param int $customer_id The new customer ID.
	 */
	public function save_consent_from_registration( $customer_id ) {
		if ( ! $this->is_enabled() ) {
			return;
		}

		// Check if the registration form was actually submitted (vs account creation via checkout).
		// We rely on the presence of the nonce specific to the registration form.
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), $this->register_nonce_action ) ) {
			// If nonce doesn't match, this likely wasn't a registration form submission (e.g., checkout account creation).
			// Let save_consent_on_account_creation_from_checkout handle it.
			return;
		}

		$consent_given = isset( $_POST[ $this->meta_key ] );
		$consent_value = $consent_given ? 'yes' : 'no';

		update_user_meta( $customer_id, $this->meta_key, $consent_value );
	}

	/**
	 * Get allowed HTML for consent forms
	 */
	protected function get_allowed_html() {
		return array(
			'div' => array(
				'class' => array(),
			),
			'label' => array(
				'for' => array(),
			),
			'input' => array(
				'type' => array(),
				'name' => array(),
				'id' => array(),
				'checked' => array(),
				'class' => array(),
			),
			'span' => array(
				'class' => array(),
			),
		);
	}

	/**
	 * Adds consent checkbox to the account details page.
	 */
	public function add_consent_checkbox_to_account() {
		if (!$this->is_enabled()) {
			$this->log_debug('Consent not enabled');
			return;
		}
		
		$this->log_debug('Adding consent checkbox to account');
		$user_id = get_current_user_id();
		$consent_value = get_user_meta($user_id, $this->meta_key, true);

		$default_html = $this->get_default_account_html();
		$account_html = get_option($this->account_html_option_key, $default_html);
		
		// Force checkbox to exist
		if (strpos($account_html, '<input type="checkbox"') === false) {
			$account_html = $default_html;
		}
		
		if ($consent_value === 'yes') {
			$account_html = str_replace(
				'type="checkbox"',
				'type="checkbox" checked="checked"',
				$account_html
			);
		}
		
		// Do NOT add nonce field here - it's already added by WooCommerce
		echo wp_kses($account_html, $this->get_allowed_html());
	}

	/**
	 * Saves consent from the account details page.
	 *
	 * @param int $user_id The user ID being saved.
	 */
	public function save_consent_from_account($user_id) {
		if (!$this->is_enabled()) {
			return;
		}

		// Verify nonce for security
		if (!isset($_POST['save-account-details-nonce']) || 
			!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['save-account-details-nonce'])), 'save_account_details')) {
			return;
		}

		$consent_value = isset($_POST[$this->meta_key]) ? 'yes' : 'no';
		update_user_meta($user_id, $this->meta_key, $consent_value);
		update_user_meta($user_id, $this->meta_key . '_updated', current_time('mysql'));
		
		$this->log_debug(sprintf('Updated %s consent for user %d to %s', $this->consent_type, $user_id, $consent_value));
	}
} 