<?php
/**
 * Admin View: Consent Settings
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
    
    <?php if ( $consent_mode === 'disabled' ) : ?>
        <div class="notice notice-warning">
            <p><?php esc_html_e( 'Consent collection is currently disabled. Enable it in Convert Cart Analytics settings to customize the consent forms.', 'woocommerce_cc_analytics' ); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="options.php">
        <?php settings_fields( 'cc_consent_settings' ); ?>
        
        <h2><?php esc_html_e( 'Checkout Page HTML', 'woocommerce_cc_analytics' ); ?></h2>
        <div class="code-editor-wrapper">
            <textarea id="checkout_html" name="checkout_html" rows="5" cols="50" class="large-text code consent-html-editor"><?php echo esc_textarea( $checkout_html ); ?></textarea>
        </div>

        <h2><?php esc_html_e( 'Registration Page HTML', 'woocommerce_cc_analytics' ); ?></h2>
        <div class="code-editor-wrapper">
            <textarea id="registration_html" name="registration_html" rows="5" cols="50" class="large-text code consent-html-editor"><?php echo esc_textarea( $registration_html ); ?></textarea>
        </div>

        <h2><?php esc_html_e( 'My Account Page HTML', 'woocommerce_cc_analytics' ); ?></h2>
        <div class="code-editor-wrapper">
            <textarea id="account_html" name="account_html" rows="5" cols="50" class="large-text code consent-html-editor"><?php echo esc_textarea( $account_html ); ?></textarea>
        </div>

        <?php submit_button(); ?>
    </form>
</div> 