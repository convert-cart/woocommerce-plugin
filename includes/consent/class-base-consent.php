<?php
declare(strict_types=1);

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

		// Registration page hooks - Update these hooks
		add_action('woocommerce_register_form', array($this, 'add_consent_checkbox_to_registration'), 20);
		add_action('woocommerce_register_form_start', array($this, 'add_registration_nonce'), 10);
		add_action('woocommerce_created_customer', array($this, 'save_consent_from_registration'), 10);

		// Checkout page hooks
		add_action('woocommerce_review_order_before_submit', array($this, 'add_consent_checkbox_to_checkout'), 15);
		add_action('woocommerce_checkout_update_order_meta', array($this, 'save_consent_from_checkout'), 10);
		add_action('woocommerce_checkout_create_order', array($this, 'save_consent_to_order'), 10);

		$this->log_debug('Hooks setup complete for ' . $this->consent_type . ' consent');
	}

	/**
	 * Add registration nonce field
	 */
	public function add_registration_nonce() {
		wp_nonce_field($this->register_nonce_action, 'woocommerce-register-nonce');
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
	protected function log_debug(string $message): void {
		if (function_exists('wc_get_logger')) {
			wc_get_logger()->debug(
				sprintf('Convert Cart %s Consent: %s', ucfirst($this->consent_type), $message),
				['source' => 'convertcart-analytics']
			);
		}
	}

	/**
	 * Adds consent checkbox to checkout.
	 */
	public function add_consent_checkbox_to_checkout() {
		if (!$this->is_enabled()) {
			return;
		}

		$default_html = $this->get_default_checkout_html();
		$checkout_html = get_option($this->checkout_html_option_key, $default_html);

		// Force checkbox to exist
		if (strpos($checkout_html, '<input type="checkbox"') === false) {
			$checkout_html = $default_html;
		}

		// Check if user is logged in and has existing consent
		if (is_user_logged_in()) {
			$user_id = get_current_user_id();
			$consent_value = get_user_meta($user_id, $this->meta_key, true);
			if ($consent_value === 'yes') {
				$checkout_html = str_replace(
					'type="checkbox"',
					'type="checkbox" checked="checked"',
					$checkout_html
				);
			}
		}

		echo wp_kses($checkout_html, $this->get_allowed_html());
	}

	/**
	 * Save consent to order meta
	 */
	public function save_consent_to_order($order) {
		if (!$this->is_enabled()) {
			return;
		}

		$consent_value = isset($_POST[$this->meta_key]) ? 'yes' : 'no';
		$order->update_meta_data($this->meta_key, $consent_value);
		$order->update_meta_data($this->meta_key . '_updated', current_time('mysql'));

		// If user is creating an account during checkout, store consent in session
		if (!is_user_logged_in() && isset($_POST['createaccount']) && $_POST['createaccount']) {
			WC()->session->set('cc_pending_' . $this->meta_key, $consent_value);
		}

		$this->log_debug(sprintf(
			'Saved %s consent to order %d: %s',
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
		if (!$this->is_enabled()) {
			$this->log_debug('Registration consent not enabled');
			return;
		}

		$this->log_debug('Adding consent checkbox to registration form');
		
		$default_html = $this->get_default_registration_html();
		$registration_html = get_option($this->registration_html_option_key, $default_html);

		// Force checkbox to exist
		if (strpos($registration_html, '<input type="checkbox"') === false) {
			$registration_html = $default_html;
		}

		// Add form-row class for proper styling
		if (strpos($registration_html, 'form-row') === false) {
			$registration_html = str_replace('class="', 'class="form-row ', $registration_html);
		}

		echo wp_kses($registration_html, $this->get_allowed_html());
	}

	/**
	 * Saves consent from the registration form.
	 */
	public function save_consent_from_registration($customer_id) {
		if (!$this->is_enabled()) {
			$this->log_debug('Registration consent not enabled');
			return;
		}

		$this->log_debug('Processing registration consent for customer ' . $customer_id);

		// Check if this is a registration form submission
		if (!isset($_POST['woocommerce-register-nonce']) || 
			!wp_verify_nonce(
				sanitize_text_field(wp_unslash($_POST['woocommerce-register-nonce'])), 
				$this->register_nonce_action
			)) {
			$this->log_debug('Invalid or missing registration nonce');
			return;
		}

		$consent_value = isset($_POST[$this->meta_key]) ? 'yes' : 'no';
		update_user_meta($customer_id, $this->meta_key, $consent_value);
		update_user_meta($customer_id, $this->meta_key . '_updated', current_time('mysql'));

		$this->log_debug(sprintf(
			'Saved %s consent from registration. Customer: %d, Value: %s',
			$this->consent_type,
			$customer_id,
			$consent_value
		));
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
	public function save_consent_from_account(int $user_id): bool {
		try {
			if (!$this->is_enabled()) {
				return false;
			}

			if (!isset($_POST['save-account-details-nonce']) || 
				!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['save-account-details-nonce'])), 'save_account_details')) {
				return false;
			}

			$raw_consent = isset($_POST[$this->meta_key]) ? 'yes' : 'no';
			$consent_value = $this->validate_consent_value($raw_consent);
			
			update_user_meta($user_id, $this->meta_key, $consent_value);
			update_user_meta($user_id, $this->meta_key . '_updated', current_time('mysql'));
			
			$this->log_debug(sprintf('Updated %s consent for user %d to %s', $this->consent_type, $user_id, $consent_value));
			return true;
		} catch (\Exception $e) {
			$this->log_debug(sprintf('Error saving %s consent: %s', $this->consent_type, $e->getMessage()));
			return false;
		}
	}

	protected function validate_consent_value($value): string {
		$valid_values = ['yes', 'no'];
		return in_array($value, $valid_values, true) ? $value : 'no';
	}

	protected function get_consent_html(string $type): string {
		$cache_key = "cc_{$this->consent_type}_{$type}_html_cache";
		$cached_html = get_transient($cache_key);
		
		if (false !== $cached_html) {
			return $cached_html;
		}
		
		$html = get_option($this->{$type . '_html_option_key'}, $this->{"get_default_{$type}_html"}());
		set_transient($cache_key, $html, HOUR_IN_SECONDS);
		
		return $html;
	}

	public function get_consent_value($user_id): string {
		$consent_value = get_user_meta($user_id, $this->meta_key, true);
		return apply_filters(
			'convertcart_' . $this->consent_type . '_consent_value',
			$consent_value,
			$user_id
		);
	}
} 