<?php
/**
 * Admin settings page for SMS consent.
 *
 * @package ConvertCart\Analytics\Admin
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="wrap">
	<h1><?php _e( 'Convert Cart SMS Consent Settings', 'woocommerce_cc_analytics' ); ?></h1>
	<form method="POST" id="convert-cart-form">
		<h2><?php _e( 'Checkout Page HTML', 'woocommerce_cc_analytics' ); ?></h2>
		<textarea id="cc_sms_consent_checkout_html" name="cc_sms_consent_checkout_html" rows="10"
			cols="50"><?php echo esc_textarea( $checkout_html ); ?></textarea>

		<h2><?php _e( 'Registration Page HTML', 'woocommerce_cc_analytics' ); ?></h2>
		<textarea id="cc_sms_consent_registration_html" name="cc_sms_consent_registration_html" rows="10"
			cols="50"><?php echo esc_textarea( $registration_html ); ?></textarea>

		<h2><?php _e( 'My Account Page HTML', 'woocommerce_cc_analytics' ); ?></h2>
		<textarea id="cc_sms_consent_account_html" name="cc_sms_consent_account_html" rows="10"
			cols="50"><?php echo esc_textarea( $account_html ); ?></textarea>

		<p><input type="submit" name="save_convert_cart_html" value="Save SMS Consent HTML Snippets" class="button-primary"></p>
	</form>
</div>

<script>
	jQuery(document).ready(function ($) {
		// Initialize CodeMirror for each textarea
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

		// JavaScript validation to check for sms_consent checkbox
		$('#convert-cart-form').on('submit', function (e) {
			var checkoutHtml = $('#cc_sms_consent_checkout_html').val();
			var registrationHtml = $('#cc_sms_consent_registration_html').val();
			var accountHtml = $('#cc_sms_consent_account_html').val();

			// Function to validate HTML structure
			function isValidHTML(...htmlArgs) {
				return htmlArgs.every(html => {
					let doc = document.createElement('div');
					doc.innerHTML = html.trim();
					return doc.innerHTML === html.trim(); // Check if it was parsed correctly
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
				e.preventDefault(); // Stop form submission
			}
		});
	});
</script> 