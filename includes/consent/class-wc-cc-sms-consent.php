<?php
declare(strict_types=1);

namespace ConvertCart\Analytics\Consent;

use WC_Integration;

/**
 * SMS consent handling.
 */
class WC_CC_SMS_Consent extends WC_CC_Consent_Base {
    protected string $consent_type = 'sms';
    protected string $option_name = 'enable_sms_consent';
    protected string $meta_key = '_convertcart_sms_consent';
    protected string $checkbox_name = 'sms_consent';

    /**
     * Constructor.
     *
     * @param WC_Integration $integration Parent integration instance.
     * @param string $plugin_url Base URL of the plugin.
     * @param string $plugin_path Base path of the plugin.
     * @param string $plugin_version Plugin version.
     */
    public function __construct(
        WC_Integration $integration,
        string $plugin_url,
        string $plugin_path,
        string $plugin_version
    ) {
        // Pass all arguments up to the parent constructor
        parent::__construct($integration, 'sms', $plugin_url, $plugin_path, $plugin_version);
    }

    public function init(): void {
        parent::init();

        error_log("ConvertCart Debug (SMS Consent): init() called. Mode: " . $this->get_consent_mode());

        if ($this->get_consent_mode() === 'disabled') {
            error_log("ConvertCart Debug (SMS Consent): init() - Disabled, skipping hooks.");
            return;
        }

        add_action('woocommerce_review_order_before_submit', [$this, 'display_consent_checkbox'], 10);
        error_log("ConvertCart Debug (SMS Consent): Added hook 'woocommerce_review_order_before_submit' for 'display_consent_checkbox'.");

        add_action('woocommerce_checkout_update_order_meta', [$this, 'save_consent_data'], 10, 2);
        error_log("ConvertCart Debug (SMS Consent): Added hook 'woocommerce_checkout_update_order_meta' for 'save_consent_data'.");

        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        error_log("ConvertCart Debug (SMS Consent): Added hook 'wp_enqueue_scripts' for 'enqueue_scripts'.");
    }

    public function display_consent_checkbox(): void {
        error_log("ConvertCart Debug (SMS Consent): display_consent_checkbox() hook fired. Is Checkout: " . (is_checkout() ? 'Yes' : 'No'));

        if (!is_checkout() || $this->get_consent_mode() === 'disabled') {
             error_log("ConvertCart Debug (SMS Consent): display_consent_checkbox() - Bailing (Not checkout or disabled).");
            return;
        }

        if ($this->is_blocks_checkout()) {
            error_log("ConvertCart Debug (SMS Consent): display_consent_checkbox() - Blocks checkout detected, JS should handle display.");
            return;
        }

        error_log("ConvertCart Debug (SMS Consent): display_consent_checkbox() - Proceeding with Classic Checkout display.");
        $checkbox_html = $this->generate_checkbox_html();
        if (!empty($checkbox_html)) {
            echo '<div class="consent-checkbox sms-consent-checkbox">';
            echo $checkbox_html;
            echo '</div>';
            error_log("ConvertCart Debug (SMS Consent): display_consent_checkbox() - Outputted HTML for Classic Checkout.");
        } else {
             error_log("ConvertCart Debug (SMS Consent): display_consent_checkbox() - generate_checkbox_html() returned empty.");
        }
    }

    public function enqueue_scripts(): void {
        error_log("ConvertCart Debug (SMS Consent): enqueue_scripts() hook fired. Is Checkout: " . (is_checkout() ? 'Yes' : 'No'));

        if (!is_checkout() || $this->get_consent_mode() === 'disabled') {
             error_log("ConvertCart Debug (SMS Consent): enqueue_scripts() - Bailing (Not checkout or disabled).");
            return;
        }

        if ($this->is_blocks_checkout()) {
            error_log("ConvertCart Debug (SMS Consent): enqueue_scripts() - Blocks checkout detected.");

            // *** Use properties instead of constants ***
            if (empty($this->plugin_url) || empty($this->plugin_path)) {
                 error_log("ConvertCart Debug (SMS Consent): ERROR - Plugin URL/Path properties are empty!");
                 return; // Cannot enqueue script without path/url
            } else {
                 error_log("ConvertCart Debug (SMS Consent): Using Plugin URL: " . $this->plugin_url);
                 error_log("ConvertCart Debug (SMS Consent): Using Plugin Path: " . $this->plugin_path);
            }

            $script_handle = 'convertcart-block-checkout-integration';
            $script_path = $this->plugin_url . 'assets/js/block-checkout-integration.js';
            $script_asset_path = $this->plugin_path . 'assets/js/block-checkout-integration.asset.php';
            $script_asset = file_exists($script_asset_path)
                ? require($script_asset_path)
                : ['dependencies' => [], 'version' => $this->plugin_version]; // Use version property

            wp_enqueue_script(
                $script_handle,
                $script_path,
                $script_asset['dependencies'],
                $script_asset['version'],
                true
            );
             error_log("ConvertCart Debug (SMS Consent): Enqueued script '$script_handle'. Path: $script_path");

            $consent_html = $this->generate_checkbox_html();
            wp_localize_script(
                $script_handle,
                'convertcart_consent_data_sms',
                ['sms_consent_html' => $consent_html]
            );
             error_log("ConvertCart Debug (SMS Consent): Localized data 'convertcart_consent_data_sms'. HTML empty: " . (empty($consent_html) ? 'Yes' : 'No'));

        } else {
             error_log("ConvertCart Debug (SMS Consent): enqueue_scripts() - Classic checkout detected, no block script needed.");
        }
    }

    protected function generate_checkbox_html(): string {
        error_log("ConvertCart Debug (SMS Consent): generate_checkbox_html() called.");
        $label = __('I consent to receive SMS communications Checkout.', 'woocommerce_cc_analytics');
        $html = '<label for="' . esc_attr($this->checkbox_name) . '">';
        $html .= '<input type="checkbox" name="' . esc_attr($this->checkbox_name) . '" id="' . esc_attr($this->checkbox_name) . '" value="yes" />';
        $html .= esc_html($label);
        $html .= '</label>';
        error_log("ConvertCart Debug (SMS Consent): generate_checkbox_html() generated HTML.");
        return $html;
    }

    protected function is_blocks_checkout(): bool {
        $is_blocks = function_exists('is_cart') && function_exists('is_checkout') && !is_cart() && !is_checkout() && function_exists('wc_is_active_theme') && wc_is_active_theme('block-based');
        if (function_exists('has_block') && has_block('woocommerce/checkout')) {
             error_log("ConvertCart Debug (Consent Base): is_blocks_checkout() - Detected 'woocommerce/checkout' block.");
            return true;
        }
        return false;
    }

    /**
     * Get default consent HTML.
     *
     * @return string
     */
    public function get_default_consent_html(): string {
        return '<p class="form-row">
            <label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
                <input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" 
                       name="sms_consent" id="sms_consent" value="yes" />
                <span>' . esc_html__('I agree to receive SMS messages about my order, account, and special offers.', 'woocommerce_cc_analytics') . '</span>
            </label>
        </p>';
    }
} 