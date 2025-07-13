<?php

namespace ConvertCart\Analytics;

defined('ABSPATH') || exit;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

class ConvertCart_Email_Consent_Block_Integration implements IntegrationInterface {

    public function get_name() {
        return 'convertcart-email-consent';
    }

    public function initialize() {
        $this->register_scripts();
        $this->register_block_type();
    }

    private function register_scripts() {
        // Check if we should register scripts (prevent classic checkout conflicts)
        if ( is_admin() || ! function_exists( 'is_checkout' ) ) {
            // In admin or when WooCommerce functions aren't available, register normally
        } elseif ( is_checkout() ) {
            // On checkout page, check if it's using blocks
            global $post;
            if ( ! $post || ! has_block( 'woocommerce/checkout', $post ) ) {
                // Classic checkout detected - don't register scripts
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
                    error_log( 'ConvertCart: Classic checkout detected, skipping Email consent script registration' );
                }
                return;
            }
        }
        
        // Use the plugin URL constant for reliable URL construction
        $plugin_url = defined('CONVERTCART_PLUGIN_URL') ? CONVERTCART_PLUGIN_URL : plugins_url('', dirname(__DIR__) . '/cc-analytics.php');

        // Register frontend script
        wp_register_script(
            'convertcart-email-consent-block-frontend',
            $plugin_url . 'assets/dist/js/email_consent/email-consent-block-frontend.js',
            array('wc-blocks-checkout', 'wp-element', 'wp-i18n', 'wp-components', 'wc-settings'),
            '1.0.0',
            true
        );

        // Register editor script
        wp_register_script(
            'convertcart-email-consent-block',
            $plugin_url . 'assets/dist/js/email_consent/email-consent-block.js',
            array('wc-blocks-checkout', 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-components', 'wp-block-editor'),
            '1.0.0',
            true
        );
    }

    public function get_script_handles() {
        return array('convertcart-email-consent-block-frontend');
    }

    public function get_editor_script_handles() {
        return array('convertcart-email-consent-block');
    }

    public function get_script_data() {
        $options = get_option('woocommerce_cc_analytics_settings', array());
        return array(
            'defaultText'      => __('I consent to email communications.', 'convertcart'),
            'trackingEnabled'  => !empty($options['cc_client_id']),
            'settings'         => $options
        );
    }

    private function register_block_type() {
        if (function_exists('register_block_type_from_metadata')) {
            $block_path = defined('CONVERTCART_PLUGIN_PATH') ? CONVERTCART_PLUGIN_PATH . 'assets/dist/js/email_consent' : dirname(__DIR__) . '/assets/dist/js/email_consent';
            register_block_type_from_metadata($block_path, [
                'view_script_handles'   => ['convertcart-email-consent-block-frontend'],
                'editor_script_handles' => ['convertcart-email-consent-block'],
            ]);
        }
    }
}
