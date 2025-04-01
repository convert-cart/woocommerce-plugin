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
	 * Constructor.
	 *
	 * @param Integration $integration Integration instance.
	 */
	public function __construct( Integration $integration ) {
		$this->integration = $integration;
		$this->set_consent_properties();
		$this->validate_properties();
		$this->setup_hooks();
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
		$options = $this->get_settings();
		return isset( $options[ $this->enable_setting_key ] ) && 'live' === $options[ $this->enable_setting_key ];
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
	 * Setup common hooks. Child classes can override or add more hooks.
	 */
	protected function setup_hooks() {
		add_action( 'woocommerce_review_order_before_submit', array( $this, 'add_consent_checkbox_to_checkout' ) );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_consent_from_checkout' ), 10, 1 );
		add_action( 'woocommerce_register_form', array( $this, 'add_consent_checkbox_to_registration' ) );
		// Note: woocommerce_created_customer runs *after* user meta might be needed by other plugins,
		// consider 'woocommerce_register_post' as well if timing is critical, but it requires more checks.
		add_action( 'woocommerce_created_customer', array( $this, 'save_consent_from_registration' ), 10, 1 );
		add_action( 'woocommerce_edit_account_form', array( $this, 'add_consent_checkbox_to_account' ) );
		add_action( 'woocommerce_save_account_details', array( $this, 'save_consent_from_account' ), 12, 1 );

		// Hook for saving consent when account is created during checkout.
		// Needs to run slightly later than the registration form save.
		add_action( 'woocommerce_created_customer', array( $this, 'save_consent_on_account_creation_from_checkout' ), 15, 1 );
	}

	/**
	 * Adds consent checkbox to checkout page.
	 */
	public function add_consent_checkbox_to_checkout() {
		if ( ! $this->is_enabled() ) {
			return;
		}
		$default_html  = $this->get_default_checkout_html();
		$checkout_html = get_option( $this->checkout_html_option_key, $default_html );
		echo wp_kses_post( $checkout_html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Saves consent from checkout to order and potentially customer meta.
	 *
	 * @param \WC_Order $order The order object.
	 */
	public function save_consent_from_checkout( $order ) {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$consent_given = isset( $_POST[ $this->meta_key ] ) && wp_verify_nonce( isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '', $this->checkout_nonce_action );
		$consent_value = $consent_given ? 'yes' : 'no';

		$order->update_meta_data( $this->meta_key, $consent_value );

		// If the user is logged in or creating an account, save to user meta.
		$user_id = $order->get_user_id();
		if ( $user_id > 0 ) {
			update_user_meta( $user_id, $this->meta_key, $consent_value );
		} elseif ( isset( $_POST['createaccount'] ) && ! empty( $_POST['account_password'] ) ) {
			// User is creating an account during checkout, but user ID isn't available yet.
			// Store the consent value temporarily to be saved when the customer is created.
			WC()->session->set( 'cc_pending_' . $this->meta_key, $consent_value );
		}
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
		// Only add if account creation on registration page is enabled.
		if ( ! get_option( 'woocommerce_enable_myaccount_registration' ) === 'yes' ) {
			return;
		}
		$default_html      = $this->get_default_registration_html();
		$registration_html = get_option( $this->registration_html_option_key, $default_html );
		echo wp_kses_post( $registration_html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
	 * Adds consent checkbox to the account details page.
	 */
	public function add_consent_checkbox_to_account() {
		if ( ! $this->is_enabled() ) {
			return;
		}
		$user_id      = get_current_user_id();
		$consent_meta = get_user_meta( $user_id, $this->meta_key, true );

		$default_html = $this->get_default_account_html();
		$account_html = get_option( $this->account_html_option_key, $default_html );

		// Inject the 'checked' attribute if consent is 'yes'.
		// This is a bit fragile; assumes a standard checkbox input structure.
		$checked_attr = checked( $consent_meta, 'yes', false );
		if ( $checked_attr ) {
			// Attempt to add 'checked' attribute intelligently.
			$account_html = preg_replace( '/(<input\s+[^>]*?name=["\']' . preg_quote( $this->meta_key, '/' ) . '["\'][^>]*?)(\/?>)/i', '$1 ' . $checked_attr . '$2', $account_html, 1 );
		}

		echo wp_kses_post( $account_html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Saves consent from the account details page.
	 *
	 * @param int $user_id The user ID being saved.
	 */
	public function save_consent_from_account( $user_id ) {
		if ( ! $this->is_enabled() ) {
			return;
		}

		// Verify nonce for security.
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), $this->account_details_nonce_action ) ) {
			return;
		}

		$consent_given = isset( $_POST[ $this->meta_key ] );
		$consent_value = $consent_given ? 'yes' : 'no';

		update_user_meta( $user_id, $this->meta_key, $consent_value );
	}
} 