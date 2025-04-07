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
	public function __construct($integration) {
		// Basic check if $integration looks usable for logging
		if (!is_object($integration)) {
			error_log('Convert Cart Base Consent Error: Integration parameter is not an object.');
			return;
		}

		// Check if it's one of our expected integration classes
		if (!($integration instanceof \WC_Integration) && !($integration instanceof \ConvertCart\Analytics\Core\Integration)) {
			error_log('Convert Cart Base Consent Error: Integration object is not of expected type.');
			return;
		}

		$this->integration = $integration;
		
		// Set properties first
		$this->set_consent_properties();
		
		// Then validate them
		try {
		$this->validate_properties();
		} catch (\Exception $e) {
			error_log('Convert Cart Base Consent Error: ' . $e->getMessage());
			return;
		}

		// Verify setup - logs settings
		$this->verify_setup();

		$this->log_debug('Base_Consent constructor finished.');
	}

	/**
	 * Abstract method to force child classes to define their specific properties.
	 */
	abstract protected function set_consent_properties();

	/**
	 * Validate required properties are set
	 *
	 * @throws \Exception If required properties are not set
	 */
	protected function validate_properties() {
		$required_properties = [
			'consent_type',
			'enable_setting_key',
			'meta_key',
			'checkout_html_option_key',
			'registration_html_option_key',
			'account_html_option_key'
		];

		foreach ($required_properties as $property) {
			if (empty($this->$property)) {
				$this->log_debug("Required property \"$property\" is not set.");
				// Don't throw, just return
				return;
			}
		}

		$this->log_debug('Properties validated successfully.');
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
	 * Check if this consent type is enabled (set to 'live' or 'draft').
	 * Allows checking for specific states like 'live'.
	 *
	 * @param string|null $state Check for a specific state ('live', 'draft'). If null, checks if not 'disabled'.
	 * @return bool True if enabled (or matches the specified state), false otherwise.
	 */
	public function is_enabled( $state = null ) {
		$settings = get_option( $this->settings_option_key, array() );
		$current_status = $settings[ $this->enable_setting_key ] ?? 'disabled';

		if ( $state ) {
			$is_enabled = ( $current_status === $state );
			$this->log_debug( sprintf(
				'Checking if %s consent is enabled for state "%s". Key: %s, Value: %s, Result: %s',
				$this->consent_type,
				$state,
				$this->enable_setting_key,
				$current_status,
				$is_enabled ? 'true' : 'false'
			) );
			return $is_enabled;
		} else {
			$is_enabled = ( $current_status !== 'disabled' );
			$this->log_debug( sprintf(
				'Checking if %s consent is enabled (not disabled). Key: %s, Value: %s, Result: %s',
				$this->consent_type,
				$this->enable_setting_key,
				$current_status,
				$is_enabled ? 'true' : 'false'
			) );
			return $is_enabled;
		}
	}

	/**
	 * Get consent HTML for a specific location (checkout, registration, account)
	 * 
	 * @param string $location The location (checkout, registration, account)
	 * @return string The HTML for the consent checkbox
	 */
	protected function get_consent_html($location) {
		$this->log_debug("Getting consent HTML for $location");
		
		// Get the option key for this location
		$option_key = $this->{$location . '_html_option_key'};
		if (empty($option_key)) {
			$this->log_debug("Invalid location: $location");
			return '';
		}
		
		// Get the default HTML method name
		$default_method = "get_default_{$location}_html";
		if (!method_exists($this, $default_method)) {
			$this->log_debug("Default method not found: $default_method");
			return '';
		}
		
		// Get the HTML from option or use default
		$default_html = $this->$default_method();
		$html = get_option($option_key, $default_html);
		
		// Check if user is logged in for account/checkout pages
		if (is_user_logged_in() && ($location === 'account' || $location === 'checkout')) {
			$user_id = get_current_user_id();
			$consent_value = get_user_meta($user_id, $this->meta_key, true);
			
			if ($consent_value === 'yes') {
				$html = str_replace(
					'type="checkbox"',
					'type="checkbox" checked="checked"',
					$html
				);
			}
		}
		
		$this->log_debug("Returning HTML for $location: " . substr($html, 0, 50) . "...");
		return $html;
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
	 * Setup all necessary hooks
	 */
	public function setup_hooks() {
		if (!$this->is_enabled()) {
			$this->log_debug('Consent not enabled, skipping hook setup');
			return;
		}
		
		$this->log_debug('Setting up hooks for ' . $this->consent_type . ' consent');
		
		// Setup common hooks (registration, account, etc.)
		$this->setup_common_hooks();
		
		// Setup checkout-specific hooks
		if (function_exists('is_checkout') && is_checkout()) {
			$this->log_debug('On checkout page, setting up checkout hooks.');
			
			// Check if using block checkout or classic checkout
			if ($this->is_block_checkout()) {
				$this->log_debug('Block checkout detected. Setting up block checkout hooks.');
				$this->setup_block_checkout_hooks();
			} else {
				$this->log_debug('Classic checkout detected. Setting up classic checkout hooks.');
				$this->setup_classic_checkout_hooks();
			}
		}
		
		// Allow child classes to add their own hooks
		$this->setup_child_hooks();
		
		$this->log_debug('All hooks setup complete for ' . $this->consent_type . ' consent.');
	}

	/**
	 * Setup common hooks for all consent types
	 */
	protected function setup_common_hooks() {
		$this->log_debug('Setting up common hooks (Registration, Account, Order Save).');
		
		// Registration form hook
		add_action('woocommerce_register_form', array($this, 'add_consent_to_registration'), 15);
		
		// My Account edit account form hook
		add_action('woocommerce_edit_account_form', array($this, 'add_consent_to_account'), 15);
		
		// Save consent from registration/account forms
		add_action('woocommerce_created_customer', array($this, 'save_consent_from_registration'), 10);
		add_action('woocommerce_save_account_details', array($this, 'save_consent_from_account'), 10);
		
		$this->log_debug('Common hooks setup complete.');
	}

	/**
	 * Add consent checkbox to registration form
	 */
	public function add_consent_to_registration() {
		echo $this->get_consent_html('registration');
	}

	/**
	 * Add consent checkbox to My Account edit form
	 */
	public function add_consent_to_account() {
		echo $this->get_consent_html('account');
	}

	/**
	 * Save consent from registration form
	 */
	public function save_consent_from_registration($customer_id) {
		$this->log_debug("Saving consent from registration for customer $customer_id");
		
		if (!$this->is_enabled()) {
			$this->log_debug("Consent not enabled, skipping save");
			return;
		}
		
		$consent_value = isset($_POST[$this->meta_key]) ? 'yes' : 'no';
		update_user_meta($customer_id, $this->meta_key, $consent_value);
		update_user_meta($customer_id, $this->meta_key . '_updated', current_time('mysql'));
		
		$this->log_debug("Saved $this->consent_type consent as $consent_value");
	}

	/**
	 * Save consent from account form
	 */
	public function save_consent_from_account($customer_id) {
		$this->log_debug("Saving consent from account for customer $customer_id");
		
		if (!$this->is_enabled()) {
			$this->log_debug("Consent not enabled, skipping save");
			return;
		}
		
		$consent_value = isset($_POST[$this->meta_key]) ? 'yes' : 'no';
		update_user_meta($customer_id, $this->meta_key, $consent_value);
		update_user_meta($customer_id, $this->meta_key . '_updated', current_time('mysql'));
		
		$this->log_debug("Saved $this->consent_type consent as $consent_value");
	}

	/**
	 * Placeholder for child classes to add specific hooks.
	 * This method should be overridden in SMS_Consent and Email_Consent if they have unique hooks.
	 */
	protected function setup_child_hooks() {
		// Example: add_action('some_sms_specific_hook', [$this, 'sms_handler']);
		$this->log_debug('Running setup_child_hooks (Base implementation - no hooks added).');
	}

	/**
	 * Verify settings and hooks
	 */
	protected function verify_setup() {
		$options = get_option($this->settings_option_key);
		$this->log_debug('Current settings: ' . print_r($options, true));
		$this->log_debug('Enable setting key: ' . $this->enable_setting_key);
		
		// Check if hook exists before trying to print it
		if (isset($GLOBALS['wp_filter']['woocommerce_after_checkout_billing_form'])) {
			$this->log_debug('Current hooks: ' . print_r($GLOBALS['wp_filter']['woocommerce_after_checkout_billing_form'], true));
		} else {
			$this->log_debug('Checkout billing form hook not yet registered');
		}
	}

	/**
	 * Debug logging helper - Uses WP_DEBUG.
	 */
	protected function log_debug($message) {
		// Always use error_log if WP_DEBUG is enabled
		if ( defined('WP_DEBUG') && WP_DEBUG === true ) {
			if ( is_array( $message ) || is_object( $message ) ) {
				$message = print_r( $message, true );
			}
			error_log(sprintf(
				'Convert Cart %s Consent Debug: %s [Hook: %s]', // Simplified prefix
				ucfirst($this->consent_type ?: 'Unknown'),
				$message,
				current_filter() ?: 'N/A'
			));
		}
	}

	/**
	 * Check if the current checkout page uses blocks.
	 * Basic check - might need refinement based on theme/setup.
	 *
	 * @return bool
	 */
	public function is_block_checkout() {
		$this->log_debug("Running is_block_checkout check...");

		if (!function_exists('is_checkout')) {
			$this->log_debug("is_block_checkout: is_checkout function doesn't exist.");
			return false;
		}

		if (!is_checkout()) {
			$this->log_debug("is_block_checkout: Not on checkout page.");
			return false;
		}

		$this->log_debug("is_block_checkout: is_checkout() returned true.");

		if (!function_exists('wc_get_page_id')) {
			$this->log_debug("is_block_checkout: wc_get_page_id function doesn't exist.");
			return false;
		}

		$checkout_page_id = wc_get_page_id('checkout');
		if (!$checkout_page_id || $checkout_page_id <= 0) {
			$this->log_debug("is_block_checkout: Invalid checkout page ID: " . print_r($checkout_page_id, true));
			return false;
		}

		$this->log_debug("is_block_checkout: Got checkout page ID: $checkout_page_id");

		$checkout_page = get_post($checkout_page_id);
		if (!$checkout_page instanceof \WP_Post) {
			$this->log_debug("is_block_checkout: Could not get checkout page post object.");
			return false;
		}

		$this->log_debug("is_block_checkout: Got checkout page post object.");

		// Check for block checkout in several ways
		$has_block = has_block('woocommerce/checkout', $checkout_page);
		$post_content = $checkout_page->post_content;
		$contains_block_comment = strpos($post_content, '<!-- wp:woocommerce/checkout -->') !== false;

		$this->log_debug(sprintf(
			"is_block_checkout: has_block: %s, contains_block_comment: %s",
			$has_block ? 'true' : 'false',
			$contains_block_comment ? 'true' : 'false'
		));

		return $has_block || $contains_block_comment;
	}

	/**
	 * Setup block-specific checkout hooks
	 */
	protected function setup_block_checkout_hooks() {
		$this->log_debug('Setting up block checkout hooks');

		// Enqueue the JavaScript needed for the block integration
		add_action('wp_enqueue_scripts', array($this, 'enqueue_block_checkout_scripts'));

		$this->log_debug('Block checkout hooks setup complete.');
	}

	/**
	 * Enqueue scripts specifically for the block checkout integration.
	 */
	public function enqueue_block_checkout_scripts() {
		// Only enqueue on the block checkout page itself
		if (!$this->is_block_checkout()) {
			return;
		}

		$script_path = plugin_dir_path(dirname(__FILE__, 2)) . 'assets/js/block-checkout-integration.js';
		$script_url = plugin_dir_url(dirname(__FILE__, 2)) . 'assets/js/block-checkout-integration.js';
		$version = file_exists($script_path) ? filemtime($script_path) : CC_PLUGIN_VERSION;

		if (!file_exists($script_path)) {
			$this->log_debug('Block checkout script file not found at: ' . $script_path);
			error_log('Convert Cart Error: Block checkout script file not found: ' . $script_path);
			return;
		}

		$script_handle = 'convertcart-block-checkout'; // Define handle

		$this->log_debug('Enqueueing block checkout script: ' . $script_url);

		wp_enqueue_script(
			$script_handle, // Use handle
			$script_url,
			array(
				'wp-blocks', 'wp-element', 'wp-html-entities', 'wp-i18n',
				'wp-plugins', 'wp-components', 'wc-blocks-checkout', 'wc-blocks-registry'
			),
			$version,
			true
		);

		// --- Add wp_localize_script ---
		// Prepare data to pass (only for the current consent type instance)
		$consent_data = [];
		if ($this->is_enabled('live')) {
			$consent_html = $this->get_consent_html('checkout');
			if (!empty($consent_html)) {
				// Use a key specific to this consent type
				$consent_data[$this->consent_type . '_consent_html'] = $consent_html;
			}
		}

		// Only localize if we have data for this specific type
		if (!empty($consent_data)) {
			$this->log_debug('Localizing script ' . $script_handle . ' with data for ' . $this->consent_type);
			error_log('Convert Cart: Localizing script for ' . $this->consent_type . ' with data: ' . print_r($consent_data, true));
			// Use wp_localize_script to make PHP data available to the script
			// Note: This will be called twice (once for SMS, once for Email if both active)
			// We'll need JS to handle potentially merging this data if the object name is the same.
			// Let's use a unique object name per type for simplicity first.
			wp_localize_script(
				$script_handle,
				'convertcart_consent_data_' . $this->consent_type, // Unique object name (e.g., convertcart_consent_data_sms)
				$consent_data // Pass the data array
			);
		} else {
			$this->log_debug('No live consent data to localize for ' . $this->consent_type);
		}
		// --- End wp_localize_script ---
	}

	/**
	 * Setup classic checkout hooks
	 */
	protected function setup_classic_checkout_hooks() {
		$this->log_debug('Setting up classic checkout hooks');
		
		// Add the consent checkbox to the checkout form
		add_action('woocommerce_after_checkout_billing_form', array($this, 'add_consent_to_checkout'), 15);
		
		// Save the consent value when order is placed
		add_action('woocommerce_checkout_update_order_meta', array($this, 'save_consent_from_checkout'), 10);
		
		$this->log_debug('Classic checkout hooks setup complete.');
	}

	/**
	 * Add consent checkbox to checkout form
	 */
	public function add_consent_to_checkout() {
		echo $this->get_consent_html('checkout');
	}

	/**
	 * Save consent from checkout
	 */
	public function save_consent_from_checkout($order_id) {
		$this->log_debug("Saving consent from checkout for order $order_id");
		
		if (!$this->is_enabled()) {
			$this->log_debug("Consent not enabled, skipping save");
			return;
		}
		
		$consent_value = isset($_POST[$this->meta_key]) ? 'yes' : 'no';
		
		// Save to order meta
		update_post_meta($order_id, '_' . $this->meta_key, $consent_value);
		
		// If user is logged in, also save to user meta
		if (is_user_logged_in()) {
			$user_id = get_current_user_id();
			update_user_meta($user_id, $this->meta_key, $consent_value);
			update_user_meta($user_id, $this->meta_key . '_updated', current_time('mysql'));
		}
		
		$this->log_debug("Saved $this->consent_type consent as $consent_value for order $order_id");
	}
} 