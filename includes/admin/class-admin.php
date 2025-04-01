<?php
/**
 * Admin functionality for Convert Cart Analytics.
 *
 * @package  ConvertCart\Analytics\Admin
 * @category Admin
 */

namespace ConvertCart\Analytics\Admin;

use ConvertCart\Analytics\Core\Integration;

class Admin {

	/**
	 * Integration instance.
	 *
	 * @var Integration
	 */
	private $integration;

	/**
	 * Constructor.
	 *
	 * @param Integration $integration Integration instance.
	 */
	public function __construct( $integration ) {
		$this->integration = $integration;
		$this->setup_hooks();
	}

	/**
	 * Setup hooks.
	 */
	private function setup_hooks() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		// Enqueue scripts needed for the settings page (CodeMirror).
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Enqueue scripts and styles for the admin settings page.
	 *
	 * @param string $hook_suffix The current admin page.
	 */
	public function enqueue_admin_scripts( $hook_suffix ) {
		// Check if we are on one of our settings pages.
		$screen = get_current_screen();
		if ( $screen && in_array( $screen->id, array( 'woocommerce_page_convert-cart-sms-consent', 'woocommerce_page_convert-cart-email-consent' ), true ) ) {
			// Enqueue CodeMirror.
			$settings = wp_enqueue_code_editor( array( 'type' => 'text/html' ) );

			// Bail if user disabled CodeMirror.
			if ( false === $settings ) {
				return;
			}

			// Enqueue our custom script and pass CodeMirror settings.
			wp_enqueue_script(
				'cc-admin-settings',
				plugin_dir_url( __FILE__ ) . 'assets/js/admin-settings.js', // We'll create this file later
				array( 'jquery', 'wp-util' ), // wp-util includes underscore.js
				CC_PLUGIN_VERSION, // Assuming CC_PLUGIN_VERSION is defined
				true
			);

			wp_localize_script(
				'cc-admin-settings',
				'ccAdminSettings',
				array(
					'codeEditorSettings' => $settings,
				)
			);
		}
	}

	/**
	 * Add admin menu items.
	 */
	public function add_admin_menu() {
		// Only show the menu if the integration is enabled.
		if ( isset( $this->integration->settings ) && 'yes' === $this->integration->settings['enabled'] ) {
			add_submenu_page(
				'woocommerce',
				__( 'Convert Cart SMS Consent', 'woocommerce_cc_analytics' ),
				__( 'CC SMS Consent', 'woocommerce_cc_analytics' ),
				'manage_options',
				'convert-cart-sms-consent',
				array( $this, 'render_convert_cart_settings_page' )
			);

			// Only show the email consent menu if email consent is enabled.
			if ( isset( $this->integration->settings['enable_email_consent'] ) && 'live' === $this->integration->settings['enable_email_consent'] ) {
				add_submenu_page(
					'woocommerce',
					__( 'Convert Cart Email Consent', 'woocommerce_cc_analytics' ),
					__( 'CC Email Consent', 'woocommerce_cc_analytics' ),
					'manage_options',
					'convert-cart-email-consent',
					array( $this, 'render_convert_cart_settings_page' )
				);
			}
		}
	}

	/**
	 * Handles saving the consent settings form.
	 *
	 * @param string $consent_type 'sms' or 'email'.
	 * @return void
	 */
	private function handle_consent_settings_save( $consent_type ) {
		$nonce_action = "save_convert_cart_{$consent_type}_html";
		$nonce_key    = '_wpnonce';
		$submit_key   = "save_convert_cart_{$consent_type}_html";
		$option_prefix = "cc_{$consent_type}_consent_";
		$checkbox_name = "{$consent_type}_consent";
		$message_key   = "convert_cart_{$consent_type}_messages";

		// Check if form is submitted with the correct nonce.
		if ( ! isset( $_POST[ $submit_key ], $_POST[ $nonce_key ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_key ] ) ), $nonce_action ) ) {
			return;
		}

		// Get submitted values (use wp_kses_post for HTML content).
		$checkout_html     = isset( $_POST[ "{$option_prefix}checkout_html" ] ) ? wp_kses_post( wp_unslash( $_POST[ "{$option_prefix}checkout_html" ] ) ) : '';
		$registration_html = isset( $_POST[ "{$option_prefix}registration_html" ] ) ? wp_kses_post( wp_unslash( $_POST[ "{$option_prefix}registration_html" ] ) ) : '';
		$account_html      = isset( $_POST[ "{$option_prefix}account_html" ] ) ? wp_kses_post( wp_unslash( $_POST[ "{$option_prefix}account_html" ] ) ) : '';

		// Basic validation: Check if the required checkbox input exists.
		// A more robust validation might involve parsing the HTML, but strpos is used here for simplicity as in the original code.
		if (
			strpos( $checkout_html, "name=\"{$checkbox_name}\"" ) !== false && strpos( $checkout_html, "id=\"{$checkbox_name}\"" ) !== false &&
			strpos( $registration_html, "name=\"{$checkbox_name}\"" ) !== false && strpos( $registration_html, "id=\"{$checkbox_name}\"" ) !== false &&
			strpos( $account_html, "name=\"{$checkbox_name}\"" ) !== false && strpos( $account_html, "id=\"{$checkbox_name}\"" ) !== false
		) {
			// Save the HTML.
			update_option( "{$option_prefix}checkout_html", $checkout_html );
			update_option( "{$option_prefix}registration_html", $registration_html );
			update_option( "{$option_prefix}account_html", $account_html );

			// Show success message.
			add_settings_error( $message_key, 'settings_updated', sprintf( __( '%s consent HTML saved successfully.', 'woocommerce_cc_analytics' ), ucfirst( $consent_type ) ), 'updated' );
		} else {
			// Show error message.
			add_settings_error( $message_key, 'checkbox_missing', sprintf( __( '%s consent HTML must contain the %s checkbox with both name and id attributes.', 'woocommerce_cc_analytics' ), ucfirst( $consent_type ), "<code>{$checkbox_name}</code>" ), 'error' );
		}
	}

	/**
	 * Prepares data needed for the consent settings view.
	 *
	 * @param string $consent_type 'sms' or 'email'.
	 * @return array Data for the view.
	 */
	private function prepare_consent_settings_data( $consent_type ) {
		$option_prefix = "cc_{$consent_type}_consent_";
		$checkbox_name = "{$consent_type}_consent";
		$consent_label = 'sms' === $consent_type ? __( 'SMS communications', 'woocommerce_cc_analytics' ) : __( 'email communications', 'woocommerce_cc_analytics' );

		// Define default HTML snippets.
		$default_checkout_html     = sprintf(
			'<div class="%1$s-consent-checkbox"><label for="%1$s_consent"><input type="checkbox" name="%1$s_consent" id="%1$s_consent"> %2$s</label></div>',
			esc_attr( $consent_type ),
			sprintf( esc_html__( 'I consent to receive %s.', 'woocommerce_cc_analytics' ), $consent_label )
		);
		$default_registration_html = sprintf(
			'<p class="form-row form-row-wide"><label for="%1$s_consent">%2$s</label><input type="checkbox" name="%1$s_consent" id="%1$s_consent"></p>',
			esc_attr( $consent_type ),
			sprintf( esc_html__( 'I consent to receive %s', 'woocommerce_cc_analytics' ), $consent_label )
		);
		$default_account_html      = $default_registration_html; // Same default for account page

		// Get saved or default HTML.
		$checkout_html     = get_option( "{$option_prefix}checkout_html", $default_checkout_html );
		$registration_html = get_option( "{$option_prefix}registration_html", $default_registration_html );
		$account_html      = get_option( "{$option_prefix}account_html", $default_account_html );

		return array(
			'consent_type'      => $consent_type,
			'page_title'        => sprintf( __( 'Convert Cart %s Consent Settings', 'woocommerce_cc_analytics' ), ucfirst( $consent_type ) ),
			'form_id'           => "convert-cart-{$consent_type}-form",
			'nonce_action'      => "save_convert_cart_{$consent_type}_html",
			'submit_button_name'=> "save_convert_cart_{$consent_type}_html",
			'submit_button_text'=> sprintf( __( 'Save %s Consent HTML Snippets', 'woocommerce_cc_analytics' ), ucfirst( $consent_type ) ),
			'message_key'       => "convert_cart_{$consent_type}_messages",
			'option_prefix'     => $option_prefix,
			'checkbox_name'     => $checkbox_name,
			'checkout_html'     => $checkout_html,
			'registration_html' => $registration_html,
			'account_html'      => $account_html,
		);
	}

	/**
	 * Renders the Convert Cart settings page (SMS or Email).
	 *
	 * @return void
	 */
	public function render_convert_cart_settings_page() {
		$consent_type = '';
		if ( isset( $_GET['page'] ) ) {
			if ( 'convert-cart-sms-consent' === $_GET['page'] ) {
				$consent_type = 'sms';
			} elseif ( 'convert-cart-email-consent' === $_GET['page'] ) {
				$consent_type = 'email';
			}
		}

		if ( empty( $consent_type ) ) {
			// Should not happen if menu items are set up correctly.
			echo '<div class="wrap"><p>' . esc_html__( 'Invalid settings page.', 'woocommerce_cc_analytics' ) . '</p></div>';
			return;
		}

		// Handle form submission if applicable.
		$this->handle_consent_settings_save( $consent_type );

		// Prepare data for the view.
		$view_data = $this->prepare_consent_settings_data( $consent_type );

		// Display settings errors (must happen after potential save).
		settings_errors( $view_data['message_key'] );

		// Load the generic view template, passing the data.
		// Use extract to make variables available directly in the view, or pass $view_data array.
		extract( $view_data, EXTR_SKIP );
		include_once plugin_dir_path( __FILE__ ) . 'views/settings-page.php'; // Include the new generic view
	}
}
