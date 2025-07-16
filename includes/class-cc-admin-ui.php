<?php
/**
 * Admin UI Class
 *
 * @package ConvertCart\Analytics
 */

namespace ConvertCart\Analytics;

class CC_Admin_UI {
	private $settings;

	public function __construct( $settings ) {
		$this->settings = $settings;
	}

	public function init_menu() {
		add_action( 'admin_menu', array( $this, 'add_convert_cart_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_codemirror_assets' ) );
	}

	public function add_convert_cart_menu() {
		$show_menu = false;

		if ( isset( $this->settings['enable_sms_consent'] ) &&
			( $this->settings['enable_sms_consent'] === 'live' || $this->settings['enable_sms_consent'] === 'draft' ) ) {
			$show_menu = true;
		}

		if ( isset( $this->settings['enable_email_consent'] ) &&
			( $this->settings['enable_email_consent'] === 'live' || $this->settings['enable_email_consent'] === 'draft' ) ) {
			$show_menu = true;
		}

		if ( $show_menu ) {
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

	public function enqueue_codemirror_assets() {
		wp_enqueue_code_editor( array( 'type' => 'text/html' ) );
		wp_enqueue_script( 'wp-theme-plugin-editor' );
		wp_enqueue_style( 'wp-codemirror' );
	}

	public function render_convert_cart_settings_page() {
		if ( isset( $this->settings['enable_sms_consent'] ) &&
			( $this->settings['enable_sms_consent'] === 'live' || $this->settings['enable_sms_consent'] === 'draft' ) ) {
			$this->render_sms_consent_form();
		}

		if ( isset( $this->settings['enable_email_consent'] ) &&
			( $this->settings['enable_email_consent'] === 'live' || $this->settings['enable_email_consent'] === 'draft' ) ) {
			$this->render_email_consent_form();
		}
	}

	private function render_sms_consent_form() {
		if ( isset( $_POST['save_convert_cart_html'] ) ) {
			$this->save_sms_consent_html();
		}

		$default_checkout_html     = '<div class="sms-consent-checkbox"><label for="sms_consent"><input type="checkbox" name="sms_consent" id="sms_consent"> I consent to receive SMS communications.</label></div>';
		$default_registration_html = '<p class="form-row form-row-wide"><label for="sms_consent">' . esc_html__( 'I consent to receive SMS communications', 'woocommerce' ) . '</label><input type="checkbox" name="sms_consent" id="sms_consent"></p>';
		$default_account_html      = '<p class="form-row form-row-wide"><label for="sms_consent">' . esc_html__( 'I consent to receive SMS communications', 'woocommerce' ) . '</label><input type="checkbox" name="sms_consent" id="sms_consent"></p>';

		$checkout_html     = get_option( 'cc_sms_consent_checkout_html', $default_checkout_html );
		$registration_html = get_option( 'cc_sms_consent_registration_html', $default_registration_html );
		$account_html      = get_option( 'cc_sms_consent_account_html', $default_account_html );

		?>
		<div class="wrap">
			<h1><?php _e( 'Convert Cart HTML Snippets', 'woocommerce_cc_analytics' ); ?></h1>
			<form method="POST" id="convert-cart-form">
				<h2><?php _e( 'Checkout Page HTML', 'woocommerce_cc_analytics' ); ?></h2>
				<textarea id="cc_sms_consent_checkout_html" name="cc_sms_consent_checkout_html" rows="10" cols="50"><?php echo esc_textarea( $checkout_html ); ?></textarea>

				<h2><?php _e( 'Registration Page HTML', 'woocommerce_cc_analytics' ); ?></h2>
				<textarea id="cc_sms_consent_registration_html" name="cc_sms_consent_registration_html" rows="10" cols="50"><?php echo esc_textarea( $registration_html ); ?></textarea>

				<h2><?php _e( 'My Account Page HTML', 'woocommerce_cc_analytics' ); ?></h2>
				<textarea id="cc_sms_consent_account_html" name="cc_sms_consent_account_html" rows="10" cols="50"><?php echo esc_textarea( $account_html ); ?></textarea>

				<p><input type="submit" name="save_convert_cart_html" value="Save SMS Consent HTML Snippets" class="button-primary"></p>
			</form>
		</div>
		<?php
		$this->render_sms_consent_javascript();
	}

	private function render_email_consent_form() {
		if ( isset( $_POST['save_convert_cart_email_html'] ) ) {
			$this->save_email_consent_html();
		}

		$default_checkout_html     = '<div class="email-consent-checkbox"><label for="email_consent"><input type="checkbox" name="email_consent" id="email_consent"> I consent to receive email communications.</label></div>';
		$default_registration_html = '<p class="form-row form-row-wide"><label for="email_consent">' . esc_html__( 'I consent to receive email communications', 'woocommerce' ) . '</label><input type="checkbox" name="email_consent" id="email_consent"></p>';
		$default_account_html      = '<p class="form-row form-row-wide"><label for="email_consent">' . esc_html__( 'I consent to receive email communications', 'woocommerce' ) . '</label><input type="checkbox" name="email_consent" id="email_consent"></p>';

		$checkout_html     = get_option( 'cc_email_consent_checkout_html', $default_checkout_html );
		$registration_html = get_option( 'cc_email_consent_registration_html', $default_registration_html );
		$account_html      = get_option( 'cc_email_consent_account_html', $default_account_html );

		?>
		<div class="wrap">
			<h1><?php _e( 'Convert Cart Email Consent', 'woocommerce_cc_analytics' ); ?></h1>
			<form method="POST" id="convert-cart-email-form">
				<h2><?php _e( 'Checkout Page HTML', 'woocommerce_cc_analytics' ); ?></h2>
				<textarea id="cc_email_consent_checkout_html" name="cc_email_consent_checkout_html" rows="10" cols="50"><?php echo esc_textarea( $checkout_html ); ?></textarea>

				<h2><?php _e( 'Registration Page HTML', 'woocommerce_cc_analytics' ); ?></h2>
				<textarea id="cc_email_consent_registration_html" name="cc_email_consent_registration_html" rows="10" cols="50"><?php echo esc_textarea( $registration_html ); ?></textarea>
				
				<h2><?php _e( 'My Account Page HTML', 'woocommerce_cc_analytics' ); ?></h2>
				<textarea id="cc_email_consent_account_html" name="cc_email_consent_account_html" rows="10" cols="50"><?php echo esc_textarea( $account_html ); ?></textarea>
					
				<p><input type="submit" name="save_convert_cart_email_html" value="Save Email Consent HTML Snippets" class="button-primary"></p>
			</form>
		</div>
		<?php
		$this->render_email_consent_javascript();
	}

	private function save_sms_consent_html() {
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
			update_option( 'cc_sms_consent_checkout_html', $cc_sms_consent_checkout_html );
			update_option( 'cc_sms_consent_registration_html', $cc_sms_consent_registration_html );
			update_option( 'cc_sms_consent_account_html', $cc_sms_consent_account_html );

			echo '<div class="updated"><p>HTML Snippets saved successfully!</p></div>';
		}
	}

	private function save_email_consent_html() {
		$cc_email_consent_checkout_html     = stripslashes( $_POST['cc_email_consent_checkout_html'] );
		$cc_email_consent_registration_html = stripslashes( $_POST['cc_email_consent_registration_html'] );
		$cc_email_consent_account_html      = stripslashes( $_POST['cc_email_consent_account_html'] );

		if (
			strpos( $cc_email_consent_checkout_html, 'name="email_consent"' ) === false ||
			strpos( $cc_email_consent_registration_html, 'name="email_consent"' ) === false ||
			strpos( $cc_email_consent_account_html, 'name="email_consent"' ) === false
		) {
			echo '<div class="error"><p>Error: The "email_consent" checkbox must be present in all snippets.</p></div>';
		} else {
			update_option( 'cc_email_consent_checkout_html', $cc_email_consent_checkout_html );
			update_option( 'cc_email_consent_registration_html', $cc_email_consent_registration_html );
			update_option( 'cc_email_consent_account_html', $cc_email_consent_account_html );

			echo '<div class="updated"><p>HTML Snippets saved successfully!</p></div>';
		}
	}

	private function render_sms_consent_javascript() {
		?>
		<script>
			jQuery(document).ready(function ($) {
				var editor_settings = wp.codeEditor.defaultSettings ? _.clone(wp.codeEditor.defaultSettings) : {};
				editor_settings.codemirror = _.extend(
					{},
					editor_settings.codemirror,
					{
						mode: 'htmlmixed',
						indentUnit: 2,
						tabSize: 2,
						lineNumbers: true,
						theme: 'default',
						lint: {
							"indentation": "tabs"
						}
					}
				);
				wp.codeEditor.initialize($('#cc_sms_consent_checkout_html'), editor_settings);
				wp.codeEditor.initialize($('#cc_sms_consent_registration_html'), editor_settings);
				wp.codeEditor.initialize($('#cc_sms_consent_account_html'), editor_settings);

				$('#convert-cart-form').on('submit', function (e) {
					var checkoutHtml = $('#cc_sms_consent_checkout_html').val();
					var registrationHtml = $('#cc_sms_consent_registration_html').val();
					var accountHtml = $('#cc_sms_consent_account_html').val();

					function isValidHTML(...htmlArgs) {
						return htmlArgs.every(html => {
							let doc = document.createElement('div');
							doc.innerHTML = html.trim();
							return doc.innerHTML === html.trim();
						});
					}

					function hasSmsConsentInputBoxWithId(...htmlArgs) {
						return htmlArgs.every(html => {
							let doc = document.createElement('div');
							doc.innerHTML = html.trim();
							const inputTag = doc.querySelector('input[name="sms_consent"]');
							return inputTag && inputTag.id === 'sms_consent' && inputTag.type === 'checkbox';
						});
					}

					try {
						if (!isValidHTML(checkoutHtml, registrationHtml, accountHtml)) {
							throw new Error('Invalid HTML detected. Please fix the HTML syntax.');
						}

						if (!hasSmsConsentInputBoxWithId(checkoutHtml, registrationHtml, accountHtml)) {
							throw new Error('The "sms_consent" checkbox must be present in all HTML snippets.');
						}
					} catch (error) {
						alert(error.message);
						e.preventDefault();
					}
				});
			});
		</script>
		<?php
	}

	private function render_email_consent_javascript() {
		?>
		<script>
			jQuery(document).ready(function ($) {
				var editor_settings = wp.codeEditor.defaultSettings ? _.clone(wp.codeEditor.defaultSettings) : {};
				editor_settings.codemirror = _.extend(
					{},
					editor_settings.codemirror,
					{
						mode: 'htmlmixed',
						indentUnit: 2,
						tabSize: 2,
						lineNumbers: true,
						theme: 'default',
						lint: {
							"indentation": "tabs"
						}
					}
				);
				wp.codeEditor.initialize($('#cc_email_consent_checkout_html'), editor_settings);
				wp.codeEditor.initialize($('#cc_email_consent_registration_html'), editor_settings);
				wp.codeEditor.initialize($('#cc_email_consent_account_html'), editor_settings);

				$('#convert-cart-email-form').on('submit', function (e) {
					var checkoutHtml = $('#cc_email_consent_checkout_html').val();
					var registrationHtml = $('#cc_email_consent_registration_html').val();
					var accountHtml = $('#cc_email_consent_account_html').val();

					function isValidHTML(...htmlArgs) {
						return htmlArgs.every(html => {
							let doc = document.createElement('div');
							doc.innerHTML = html.trim();
							return doc.innerHTML === html.trim();
						});
					}

					function hasEmailConsentInputBoxWithId(...htmlArgs) {
						return htmlArgs.every(html => {
							let doc = document.createElement('div');
							doc.innerHTML = html.trim();
							const inputTag = doc.querySelector('input[name="email_consent"]');
							return inputTag && inputTag.id === 'email_consent' && inputTag.type === 'checkbox';
						});
					}

					try {
						if (!isValidHTML(checkoutHtml, registrationHtml, accountHtml)) {
							throw new Error('Invalid HTML detected. Please fix the HTML syntax.');
						}

						if (!hasEmailConsentInputBoxWithId(checkoutHtml, registrationHtml, accountHtml)) {
							throw new Error('The "email_consent" checkbox must be present in all HTML snippets.');
						}
					} catch (error) {
						alert(error.message);
						e.preventDefault();
					}
				});
			});
		</script>
		<?php
	}
}
