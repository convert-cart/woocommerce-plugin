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
		add_action( 'admin_menu', array( $this, 'add_convert_cart_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_codemirror_assets' ) );
	}

	/**
	 * Adds Convert Cart menu to the admin dashboard.
	 *
	 * @return void
	 */
	public function add_convert_cart_menu() {
		$options = get_option( 'woocommerce_cc_analytics_settings' );

		// Only show the menu if SMS consent is enabled
		if ( isset( $options['enable_sms_consent'] ) && ( $options['enable_sms_consent'] === 'live' || $options['enable_sms_consent'] === 'draft' ) ) {
			add_menu_page(
				__( 'Convert Cart', 'woocommerce_cc_analytics' ),
				__( 'Convert Cart', 'woocommerce_cc_analytics' ),
				'manage_options',
				'convert-cart-settings',
				array( $this, 'render_convert_cart_settings_page' ),
				'dashicons-edit',
				60
			);
		}

		// Only show the menu if email consent is enabled
		if ( isset( $options['enable_email_consent'] ) && ( $options['enable_email_consent'] === 'live' || $options['enable_email_consent'] === 'draft' ) ) {
			add_menu_page(
				__( 'Convert Cart', 'woocommerce_cc_analytics' ),
				__( 'Convert Cart', 'woocommerce_cc_analytics' ),
				'manage_options',
				'convert-cart-settings',
				array( $this, 'render_convert_cart_settings_page' ),
				'dashicons-edit',
				60
			);
		}
	}

	/**
	 * Enqueues CodeMirror assets for the Convert Cart settings page.
	 *
	 * @return void
	 */
	public function enqueue_codemirror_assets() {
		wp_enqueue_code_editor( array( 'type' => 'text/html' ) );
		wp_enqueue_script( 'wp-theme-plugin-editor' );
		wp_enqueue_style( 'wp-codemirror' );
	}

	/**
	 * Renders the Convert Cart settings page.
	 *
	 * @return void
	 */
	public function render_convert_cart_settings_page() {
		$options = get_option( 'woocommerce_cc_analytics_settings' );
		if ( isset( $options['enable_sms_consent'] ) && ( $options['enable_sms_consent'] === 'live' || $options['enable_sms_consent'] === 'draft' ) ) {
			if ( isset( $_POST['save_convert_cart_html'] ) ) {
				// PHP Validation to ensure the sms_consent checkbox is present
				$cc_sms_consent_checkout_html     = stripslashes( $_POST['cc_sms_consent_checkout_html'] );
				$cc_sms_consent_registration_html = stripslashes( $_POST['cc_sms_consent_registration_html'] );
				$cc_sms_consent_account_html      = stripslashes( $_POST['cc_sms_consent_account_html'] );
				if (
					strpos( $cc_sms_consent_checkout_html, 'name="sms_consent"' ) === false ||
					strpos( $cc_sms_consent_registration_html, 'name="sms_consent"' ) === false ||
					strpos( $cc_sms_consent_account_html, 'name="sms_consent"' ) === false
				) {
					echo '<div class="error"><p>Error: The "sms_consent" checkbox must be present in all snippets.</p></div>';
				} else {
					// Save custom HTML snippets to options if valid
					update_option( 'cc_sms_consent_checkout_html', $cc_sms_consent_checkout_html );
					update_option( 'cc_sms_consent_registration_html', $cc_sms_consent_registration_html );
					update_option( 'cc_sms_consent_account_html', $cc_sms_consent_account_html );

					echo '<div class="updated"><p>HTML Snippets saved successfully!</p></div>';
				}
			}

			// Default HTML snippets as fallback
			$default_checkout_html = '<div class="sms-consent-checkbox"><label for="sms_consent"><input type="checkbox" name="sms_consent" id="sms_consent"> I consent to receive SMS communications.</label></div>';

			$default_registration_html = '<p class="form-row form-row-wide"><label for="sms_consent">' . esc_html__( 'I consent to receive SMS communications', 'woocommerce' ) . '</label><input type="checkbox" name="sms_consent" id="sms_consent"></p>';

			$default_account_html = '<p class="form-row form-row-wide"><label for="sms_consent">' . esc_html__( 'I consent to receive SMS communications', 'woocommerce' ) . '</label><input type="checkbox" name="sms_consent" id="sms_consent"></p>';

			// Get the saved HTML snippets or use defaults
			$checkout_html     = get_option( 'cc_sms_consent_checkout_html', $default_checkout_html );
			$registration_html = get_option( 'cc_sms_consent_registration_html', $default_registration_html );
			$account_html      = get_option( 'cc_sms_consent_account_html', $default_account_html );

			include_once plugin_dir_path( __FILE__ ) . 'views/sms-settings-page.php';
		}
		
		if ( isset( $options['enable_email_consent'] ) && ( $options['enable_email_consent'] === 'live' || $options['enable_email_consent'] === 'draft' ) ) {
			$options = get_option( 'woocommerce_cc_analytics_settings' );
			if ( isset( $options['enable_email_consent'] ) && ( $options['enable_email_consent'] === 'live' || $options['enable_email_consent'] === 'draft' ) ) {
				if ( isset( $_POST['save_convert_cart_email_html'] ) ) {
					// PHP Validation to ensure the email_consent checkbox is present
					$cc_email_consent_checkout_html = stripslashes( $_POST['cc_email_consent_checkout_html'] );
					$cc_email_consent_registration_html = stripslashes( $_POST['cc_email_consent_registration_html'] );
					$cc_email_consent_account_html = stripslashes( $_POST['cc_email_consent_account_html'] );
					
					if (
						strpos( $cc_email_consent_checkout_html, 'name="email_consent"' ) === false ||
						strpos( $cc_email_consent_registration_html, 'name="email_consent"' ) === false ||
						strpos( $cc_email_consent_account_html, 'name="email_consent"' ) === false
					) {
						echo '<div class="error"><p>Error: The "email_consent" checkbox must be present in all snippets.</p></div>';
					} else {
						// Save custom HTML snippets to options if valid
						update_option( 'cc_email_consent_checkout_html', $cc_email_consent_checkout_html );
						update_option( 'cc_email_consent_registration_html', $cc_email_consent_registration_html );
						update_option( 'cc_email_consent_account_html', $cc_email_consent_account_html );

						echo '<div class="updated"><p>HTML Snippets saved successfully!</p></div>';
					}
				}
				// Default HTML snippets as fallback
				$default_checkout_html = '<div class="email-consent-checkbox"><label for="email_consent"><input type="checkbox" name="email_consent" id="email_consent"> I consent to receive email communications.</label></div>';

				$default_registration_html = '<p class="form-row form-row-wide"><label for="email_consent">' . esc_html__( 'I consent to receive email communications', 'woocommerce' ) . '</label><input type="checkbox" name="email_consent" id="email_consent"></p>';

				$default_account_html = '<p class="form-row form-row-wide"><label for="email_consent">' . esc_html__( 'I consent to receive email communications', 'woocommerce' ) . '</label><input type="checkbox" name="email_consent" id="email_consent"></p>';
				
				// Get the saved HTML snippets or use defaults
				$checkout_html     = get_option( 'cc_email_consent_checkout_html', $default_checkout_html );
				$registration_html = get_option( 'cc_email_consent_registration_html', $default_registration_html );
				$account_html      = get_option( 'cc_email_consent_account_html', $default_account_html );

				include_once plugin_dir_path( __FILE__ ) . 'views/email-settings-page.php';
			}
		}
	}
} 