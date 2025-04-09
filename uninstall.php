<?php
/**
 * Convert Cart Analytics Uninstaller
 *
 * @package ConvertCart\Analytics
 */

// Exit if accessed directly.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Clean up all plugin options
$options_to_delete = [
    // Main plugin settings
    'woocommerce_cc_analytics_settings',
    
    // SMS Consent HTML options
    'cc_sms_consent_checkout_html',
    'cc_sms_consent_registration_html',
    'cc_sms_consent_account_html',
    
    // Email Consent HTML options
    'cc_email_consent_checkout_html',
    'cc_email_consent_registration_html',
    'cc_email_consent_account_html'
];

foreach ($options_to_delete as $option) {
    delete_option($option);
}

// Clear any transients
delete_transient('cc_consent_sms_updated');
delete_transient('cc_consent_email_updated'); 