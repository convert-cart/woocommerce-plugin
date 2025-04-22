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
	 *
	 * @var string
	 */
	protected $enable_setting_key = '';

	/**
	 * The meta key used to store consent in order and user meta.
	 *
	 * @var string
	 */
	protected $meta_key = '';

	/**
	 * The option key for the checkout page HTML snippet.
	 *
	 * @var string
	 */
	protected $checkout_html_option_key = '';

	/**
	 * The option key for the registration page HTML snippet.
	 *
	 * @var string
	 */
	protected $registration_html_option_key = '';

	/**
	 * The option key for the account page HTML snippet.
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
	 * Constructor
	 *
	 * @param Integration $integration The integration instance.
	 */
	public function __construct($integration) {
		if (!is_object($integration)) {
			throw new \Exception('Integration must be an object');
		}
		
		$this->integration = $integration;
		
		if (empty($this->consent_type)) {
			throw new \Exception('Consent type must be set in child class');
		}
		
		if (empty($this->enable_setting_key) || empty($this->meta_key) || 
			empty($this->checkout_html_option_key) || empty($this->registration_html_option_key) || 
			empty($this->account_html_option_key)) {
			throw new \Exception('Required properties not set in child class');
		}
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
				return;
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
	 * Check if this consent type is enabled (set to 'live' or 'draft').
	 *
	 * @param string|null $state Check for a specific state ('live', 'draft').
	 * @return bool True if enabled (or matches the specified state).
	 */
	public function is_enabled( $state = null ) {
		$settings = get_option( $this->settings_option_key, array() );
		$current_status = $settings[ $this->enable_setting_key ] ?? 'disabled';

		if ( $state ) {
			return ( $current_status === $state );
		} else {
			return ( $current_status !== 'disabled' );
		}
	}

	/**
	 * Get consent HTML for a specific location (checkout, registration, account)
	 * 
	 * @param string $location The location (checkout, registration, account)
	 * @return string The HTML for the consent checkbox
	 */
	protected function get_consent_html($location) {
		$option_key = $this->{$location . '_html_option_key'};
		if (empty($option_key)) {
			return '';
		}
		
		$default_method = "get_default_{$location}_html";
		if (!method_exists($this, $default_method)) {
			return '';
		}
		
		$default_html = $this->$default_method();
		$html = get_option($option_key, $default_html);
		
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
		
		return $html;
	}

	/**
	 * Get the default HTML for the checkout page.
	 *
	 * @return string
	 */
	abstract protected function get_default_checkout_html();

	/**
	 * Get the default HTML for the registration page.
	 *
	 * @return string
	 */
	abstract protected function get_default_registration_html();

	/**
	 * Get the default HTML for the account page.
	 *
	 * @return string
	 */
	abstract protected function get_default_account_html();

	/**
	 * Setup all necessary hooks
	 */
	public function setup_hooks() {
		if (!$this->is_enabled()) {
			return;
		}
		
		$this->setup_common_hooks();
		
		if (function_exists('is_checkout') && is_checkout()) {
			if ($this->is_block_checkout()) {
				$this->setup_block_checkout_hooks();
			} else {
				$this->setup_classic_checkout_hooks();
			}
		}
		
		$this->setup_child_hooks();
	}

	/**
	 * Setup common hooks for all consent types
	 */
	protected function setup_common_hooks() {
		add_action('woocommerce_register_form', array($this, 'add_consent_to_registration'), 15);
		add_action('woocommerce_edit_account_form', array($this, 'add_consent_to_account'), 15);
		add_action('woocommerce_created_customer', array($this, 'save_consent_from_registration'), 10);
		add_action('woocommerce_save_account_details', array($this, 'save_consent_from_account'), 10);
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
	 *
	 * @param int $customer_id Customer ID.
	 */
	public function save_consent_from_registration($customer_id) {
		if (!$this->is_enabled()) {
			return;
		}
		
		$consent_value = isset($_POST[$this->meta_key]) ? 'yes' : 'no';
		update_user_meta($customer_id, $this->meta_key, $consent_value);
		update_user_meta($customer_id, $this->meta_key . '_updated', current_time('mysql'));
	}

	/**
	 * Save consent from account form
	 *
	 * @param int $customer_id Customer ID.
	 */
	public function save_consent_from_account($customer_id) {
		if (!$this->is_enabled()) {
			return;
		}
		
		$consent_value = isset($_POST[$this->meta_key]) ? 'yes' : 'no';
		update_user_meta($customer_id, $this->meta_key, $consent_value);
		update_user_meta($customer_id, $this->meta_key . '_updated', current_time('mysql'));
	}

	/**
	 * Placeholder for child classes to add specific hooks.
	 */
	protected function setup_child_hooks() {
		// To be implemented by child classes if needed
	}

	/**
	 * Debug logging helper - Uses WP_DEBUG.
	 *
	 * @param string $message Message to log.
	 */
	protected function log_debug($message) {
		if (defined('WP_DEBUG') && WP_DEBUG === true) {
			if (is_array($message) || is_object($message)) {
				$message = print_r($message, true);
			}
			error_log(sprintf(
				'Convert Cart %s Consent: %s',
				ucfirst($this->consent_type ?: 'Unknown'),
				$message
			));
		}
	}

	/**
	 * Check if the current checkout page uses blocks.
	 *
	 * @return bool
	 */
	public function is_block_checkout() {
		if (!function_exists('is_checkout') || !is_checkout()) {
			return false;
		}

		if (!function_exists('wc_get_page_id')) {
			return false;
		}

		$checkout_page_id = wc_get_page_id('checkout');
		if (!$checkout_page_id || $checkout_page_id <= 0) {
			return false;
		}

		$checkout_page = get_post($checkout_page_id);
		if (!$checkout_page instanceof \WP_Post) {
			return false;
		}

		$has_block = has_block('woocommerce/checkout', $checkout_page);
		$post_content = $checkout_page->post_content;
		$contains_block_comment = strpos($post_content, '<!-- wp:woocommerce/checkout -->') !== false;

		return $has_block || $contains_block_comment;
	}

	/**
	 * Setup block-specific checkout hooks
	 */
	protected function setup_block_checkout_hooks() {
		add_action('wp_enqueue_scripts', array($this, 'enqueue_block_checkout_scripts'));
	}

	/**
	 * Enqueue scripts specifically for the block checkout integration.
	 */
	public function enqueue_block_checkout_scripts() {
		if (!$this->is_block_checkout()) {
			return;
		}

		$script_path = plugin_dir_path(dirname(__FILE__, 2)) . 'assets/js/block-checkout-integration.js';
		$script_url = plugin_dir_url(dirname(__FILE__, 2)) . 'assets/js/block-checkout-integration.js';
		$version = file_exists($script_path) ? filemtime($script_path) : CC_PLUGIN_VERSION;

		if (!file_exists($script_path)) {
			return;
		}

		$script_handle = 'convertcart-block-checkout';

		wp_enqueue_script(
			$script_handle,
			$script_url,
			array(
				'wp-blocks', 'wp-element', 'wp-html-entities', 'wp-i18n',
				'wp-plugins', 'wp-components', 'wc-blocks-checkout', 'wc-blocks-registry'
			),
			$version,
			true
		);

		$consent_data = [];
		if ($this->is_enabled('live')) {
			$consent_html = $this->get_consent_html('checkout');
			if (!empty($consent_html)) {
				$consent_data[$this->consent_type . '_consent_html'] = $consent_html;
			}
		}

		if (!empty($consent_data)) {
			wp_localize_script(
				$script_handle,
				'convertcart_consent_data_' . $this->consent_type,
				$consent_data
	}
}

/**
 * Setup classic checkout hooks for consent fields.
 *
 * Follows WooCommerce best practices:
 * - https://woocommerce.com/document/tutorial-customising-checkout-fields-using-actions-and-filters/
 * - https://woocommerce.com/document/woocommerce-checkout/
 *
 * Hooks:
 * - woocommerce_after_checkout_billing_form: Display custom consent fields.
 * - woocommerce_checkout_update_order_meta: Save consent field values to order meta.
 */
protected function setup_classic_checkout_hooks() {
	add_action('woocommerce_after_checkout_billing_form', array($this, 'add_consent_to_checkout'), 15);
	add_action('woocommerce_checkout_update_order_meta', array($this, 'save_consent_from_checkout'), 10);
}

/**
 * Add consent checkbox to checkout form
 */
public function add_consent_to_checkout() {
	echo $this->get_consent_html('checkout');
}
	 */
	public function add_consent_to_checkout() {
		echo $this->get_consent_html('checkout');
	}

	/**
	 * Save consent from checkout
	 *
	 * @param int $order_id Order ID.
	 */
	public function save_consent_from_checkout($order_id) {
		if (!$this->is_enabled()) {
			return;
		}
		
		$consent_value = isset($_POST[$this->meta_key]) ? 'yes' : 'no';
		
		// Save to order meta - HPOS compatible
		$order = wc_get_order($order_id);
		if ($order) {
			$order_consent = $order->get_meta($this->meta_key, true);
			$order->update_meta_data('_' . $this->meta_key, $consent_value);
			$order->save();
		} else {
			update_post_meta($order_id, '_' . $this->meta_key, $consent_value);
		}
		
		// If user is logged in, also save to user meta
		if (is_user_logged_in()) {
			$user_id = get_current_user_id();
			update_user_meta($user_id, $this->meta_key, $consent_value);
			update_user_meta($user_id, $this->meta_key . '_updated', current_time('mysql'));
		}
	}

	/**
	 * Get consent value with caching
	 */
	public function get_consent_value($user_id) {
		$cache_key = 'cc_consent_' . $this->consent_type . '_' . $user_id;
		$cached_value = get_transient($cache_key);
		
		if ($cached_value !== false) {
			return $cached_value;
		}
		
		$consent_value = get_user_meta($user_id, $this->meta_key, true);
		set_transient($cache_key, $consent_value, HOUR_IN_SECONDS);
		
		return $consent_value;
	}
} 