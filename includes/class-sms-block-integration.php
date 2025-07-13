<?php

namespace ConvertCart\Analytics;

defined('ABSPATH') || exit;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

class ConvertCart_SMS_Consent_Block_Integration implements IntegrationInterface {

    /**
     * The name of the integration.
     *
     * @return string
     */
    public function get_name() {
        return 'convertcart-sms-consent';
    }

    /**
     * Initialize the integration.
     */
    public function initialize() {
        $this->register_scripts();
        $this->register_block_type();
    }

    /**
     * Register scripts for the integration.
     */
    private function register_scripts() {
        // Use the plugin URL constant for reliable URL construction
        $plugin_url = defined('CONVERTCART_PLUGIN_URL') ? CONVERTCART_PLUGIN_URL : plugins_url('', dirname(__DIR__) . '/cc-analytics.php');
        
        // Debug: Log the generated URLs
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
            error_log( 'SMS Consent Plugin URL: ' . $plugin_url );
            error_log( 'SMS Frontend Script URL: ' . $plugin_url . 'assets/dist/js/sms_consent/sms-consent-block-frontend.js' );
        }

        // Register frontend script
        wp_register_script(
            'convertcart-sms-consent-block-frontend',
            $plugin_url . 'assets/dist/js/sms_consent/sms-consent-block-frontend.js',
            array('wc-blocks-checkout', 'wp-element', 'wp-i18n', 'wp-components', 'wc-settings'),
            '1.0.0',
            true
        );

        // Register editor script
        wp_register_script(
            'convertcart-sms-consent-block',
            $plugin_url . 'assets/dist/js/sms_consent/sms-consent-block.js',
            array('wc-blocks-checkout', 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-components', 'wp-block-editor'),
            '1.0.0',
            true
        );
    }

    /**
     * Returns an array of script handles to enqueue in the frontend context.
     *
     * @return string[]
     */
    public function get_script_handles() {
        return array('convertcart-sms-consent-block-frontend');
    }

    /**
     * Returns an array of script handles to enqueue in the editor context.
     *
     * @return string[]
     */
    public function get_editor_script_handles() {
        return array('convertcart-sms-consent-block');
    }

    /**
     * An array of key, value pairs of data made available to the block on the client side.
     *
     * @return array
     */
    public function get_script_data() {
        $options = get_option('woocommerce_cc_analytics_settings', array());
        return array(
            'defaultText'      => __('I consent to SMS communications.', 'convertcart'),
            'trackingEnabled' => !empty($options['cc_client_id']),
            'settings'         => $options // For debugging/visibility
        );
    }

    /**
     * Register the block type with script overrides to prevent broken URLs.
     */
    private function register_block_type() {
        if (function_exists('register_block_type_from_metadata')) {
            $block_path = defined('CONVERTCART_PLUGIN_PATH') ? CONVERTCART_PLUGIN_PATH . 'assets/dist/js/sms_consent' : dirname(__DIR__) . '/assets/dist/js/sms_consent';
            register_block_type_from_metadata($block_path, [
                'view_script_handles'   => ['convertcart-sms-consent-block-frontend'],
                'editor_script_handles' => ['convertcart-sms-consent-block'],
            ]);
        }
    }
}
