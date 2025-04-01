<?php
/**
 * Admin settings page for Consent HTML (Generic).
 *
 * @package ConvertCart\Analytics\Admin\Views
 *
 * Variables passed from Admin class:
 * @var string $consent_type 'sms' or 'email'.
 * @var string $page_title The title for the settings page.
 * @var string $form_id The ID for the form element.
 * @var string $nonce_action The action name for wp_nonce_field.
 * @var string $submit_button_name The name attribute for the submit button.
 * @var string $submit_button_text The text for the submit button.
 * @var string $option_prefix Prefix for the textarea names/ids (e.g., 'cc_sms_consent_').
 * @var string $checkbox_name The required checkbox name (e.g., 'sms_consent').
 * @var string $checkout_html Current HTML for the checkout page.
 * @var string $registration_html Current HTML for the registration page.
 * @var string $account_html Current HTML for the account page.
 */

defined( 'ABSPATH' ) || exit;

// Ensure variables are set to avoid notices if extract fails or file is accessed directly.
$consent_type       = isset( $consent_type ) ? $consent_type : '';
$page_title         = isset( $page_title ) ? $page_title : __( 'Consent Settings', 'woocommerce_cc_analytics' );
$form_id            = isset( $form_id ) ? $form_id : 'convert-cart-consent-form';
$nonce_action       = isset( $nonce_action ) ? $nonce_action : 'save_convert_cart_consent_html';
$submit_button_name = isset( $submit_button_name ) ? $submit_button_name : 'save_convert_cart_consent_html';
$submit_button_text = isset( $submit_button_text ) ? $submit_button_text : __( 'Save Consent HTML Snippets', 'woocommerce_cc_analytics' );
$option_prefix      = isset( $option_prefix ) ? $option_prefix : "cc_{$consent_type}_consent_";
$checkbox_name      = isset( $checkbox_name ) ? $checkbox_name : "{$consent_type}_consent";
$checkout_html      = isset( $checkout_html ) ? $checkout_html : '';
$registration_html  = isset( $registration_html ) ? $registration_html : '';
$account_html       = isset( $account_html ) ? $account_html : '';

?>

<div class="wrap">
	<h1><?php echo esc_html( $page_title ); ?></h1>
	<form method="POST" id="<?php echo esc_attr( $form_id ); ?>" data-consent-type="<?php echo esc_attr( $consent_type ); ?>" data-checkbox-name="<?php echo esc_attr( $checkbox_name ); ?>">
		<?php wp_nonce_field( $nonce_action ); ?>

		<h2><?php esc_html_e( 'Checkout Page HTML', 'woocommerce_cc_analytics' ); ?></h2>
		<textarea id="<?php echo esc_attr( "{$option_prefix}checkout_html" ); ?>" name="<?php echo esc_attr( "{$option_prefix}checkout_html" ); ?>" rows="10" cols="50" class="codemirror-textarea"><?php echo esc_textarea( $checkout_html ); ?></textarea>
		<p class="description">
			<?php
			printf(
				/* translators: %s: Required checkbox input code */
				esc_html__( 'Ensure this HTML contains the checkbox: %s', 'woocommerce_cc_analytics' ),
				'<code>&lt;input type="checkbox" name="' . esc_attr( $checkbox_name ) . '" id="' . esc_attr( $checkbox_name ) . '"&gt;</code>'
			);
			?>
		</p>

		<h2><?php esc_html_e( 'Registration Page HTML', 'woocommerce_cc_analytics' ); ?></h2>
		<textarea id="<?php echo esc_attr( "{$option_prefix}registration_html" ); ?>" name="<?php echo esc_attr( "{$option_prefix}registration_html" ); ?>" rows="10" cols="50" class="codemirror-textarea"><?php echo esc_textarea( $registration_html ); ?></textarea>
		<p class="description">
			<?php
			printf(
				/* translators: %s: Required checkbox input code */
				esc_html__( 'Ensure this HTML contains the checkbox: %s', 'woocommerce_cc_analytics' ),
				'<code>&lt;input type="checkbox" name="' . esc_attr( $checkbox_name ) . '" id="' . esc_attr( $checkbox_name ) . '"&gt;</code>'
			);
			?>
		</p>

		<h2><?php esc_html_e( 'My Account Page HTML', 'woocommerce_cc_analytics' ); ?></h2>
		<textarea id="<?php echo esc_attr( "{$option_prefix}account_html" ); ?>" name="<?php echo esc_attr( "{$option_prefix}account_html" ); ?>" rows="10" cols="50" class="codemirror-textarea"><?php echo esc_textarea( $account_html ); ?></textarea>
		<p class="description">
			<?php
			printf(
				/* translators: %s: Required checkbox input code */
				esc_html__( 'Ensure this HTML contains the checkbox: %s', 'woocommerce_cc_analytics' ),
				'<code>&lt;input type="checkbox" name="' . esc_attr( $checkbox_name ) . '" id="' . esc_attr( $checkbox_name ) . '"&gt;</code>'
			);
			?>
		</p>

		<p><input type="submit" name="<?php echo esc_attr( $submit_button_name ); ?>" value="<?php echo esc_attr( $submit_button_text ); ?>" class="button-primary"></p>
	</form>
</div>
<?php // JavaScript will be loaded via wp_enqueue_script in class-admin.php ?> 