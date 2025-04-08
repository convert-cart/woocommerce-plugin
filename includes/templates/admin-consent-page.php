<?php
/**
 * Admin consent page template.
 *
 * @var string $type Consent type (sms|email)
 * @var string $consent_mode Current consent mode
 * @var array $html_values HTML values for each context
 */

defined('ABSPATH') || exit;

$title = sprintf(
    /* translators: %s: Consent type (SMS/Email) */
    esc_html__('%s Consent Settings', 'woocommerce_cc_analytics'),
    strtoupper($type)
);
?>
<div class="wrap">
    <h1><?php echo esc_html($title); ?></h1>

    <?php if ($consent_mode === 'disabled') : ?>
        <div class="notice notice-warning">
            <p>
                <?php
                printf(
                    /* translators: %s: Consent type (SMS/Email) */
                    esc_html__('%s Consent collection is currently disabled. Enable it in Convert Cart Analytics settings to customize the consent forms.', 'woocommerce_cc_analytics'),
                    strtoupper($type)
                );
                ?>
            </p>
        </div>
    <?php endif; ?>

    <form method="post" id="convert-cart-form" action="options.php">
        <?php settings_fields('cc_consent_settings'); ?>

        <h2><?php esc_html_e('Checkout Page', 'woocommerce_cc_analytics'); ?></h2>
        <textarea name="cc_<?php echo esc_attr($type); ?>_consent_checkout_html" 
                  class="consent-html-editor large-text code" 
                  data-consent-type="<?php echo esc_attr($type); ?>" 
                  rows="10"><?php echo esc_textarea(get_option("cc_{$type}_consent_checkout_html")); ?></textarea>

        <h2><?php esc_html_e('Registration Page', 'woocommerce_cc_analytics'); ?></h2>
        <textarea name="cc_<?php echo esc_attr($type); ?>_consent_registration_html" 
                  class="consent-html-editor large-text code" 
                  data-consent-type="<?php echo esc_attr($type); ?>" 
                  rows="10"><?php echo esc_textarea(get_option("cc_{$type}_consent_registration_html")); ?></textarea>

        <h2><?php esc_html_e('Account Page', 'woocommerce_cc_analytics'); ?></h2>
        <textarea name="cc_<?php echo esc_attr($type); ?>_consent_account_html" 
                  class="consent-html-editor large-text code" 
                  data-consent-type="<?php echo esc_attr($type); ?>" 
                  rows="10"><?php echo esc_textarea(get_option("cc_{$type}_consent_account_html")); ?></textarea>

        <div class="submit-buttons" style="display: flex; gap: 10px; margin-top: 20px;">
            <?php submit_button(sprintf(
                /* translators: %s: Consent type (SMS/Email) */
                __('Save %s Consent Settings', 'woocommerce_cc_analytics'),
                strtoupper($type)
            ), 'primary', 'submit', false); ?>

            <input type="submit" name="restore_all" id="restore_all_button"
                value="<?php esc_attr_e('Restore All to Default', 'woocommerce_cc_analytics'); ?>"
                class="button button-secondary">
        </div>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Add confirmation to the Restore All button
    $('#restore_all_button').on('click', function(e) {
        if (!confirm('<?php echo esc_js(__('Are you sure you want to restore all HTML templates to default? This cannot be undone.', 'woocommerce_cc_analytics')); ?>')) {
            e.preventDefault();
        }
    });

    // Bypass validation for the Restore All button
    $('#convert-cart-form').on('submit', function(e) {
        if (e.originalEvent && e.originalEvent.submitter && e.originalEvent.submitter.name === 'restore_all') {
            return true;
        }
    });
});
</script> 