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

        if ($this->get_consent_mode() === 'disabled') {
            return;
        }

        add_action('woocommerce_review_order_before_submit', [$this, 'display_consent_checkbox'], 10);

        add_action('woocommerce_checkout_update_order_meta', [$this, 'save_consent_data'], 10, 2);

        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    public function display_consent_checkbox(): void {
        if (!is_checkout() || $this->get_consent_mode() === 'disabled') {
            return;
        }

        if ($this->is_blocks_checkout()) {
            return;
        }

        $checkbox_html = $this->generate_checkbox_html();
        if (!empty($checkbox_html)) {
            echo '<div class="consent-checkbox sms-consent-checkbox">';
            echo $checkbox_html;
            echo '</div>';
        }
    }

    public function enqueue_scripts(): void {
        // Only enqueue JS for block-based checkout. Do nothing for classic checkout.
        if (!is_checkout() || $this->get_consent_mode() === 'disabled') {
            return;
        }
    }

    protected function generate_checkbox_html(): string {
        $label = __('I consent to receive SMS communications Checkout.', 'woocommerce_cc_analytics');
        $html = '<label for="' . esc_attr($this->checkbox_name) . '">';
        $html .= '<input type="checkbox" name="' . esc_attr($this->checkbox_name) . '" id="' . esc_attr($this->checkbox_name) . '" value="yes" />';
        $html .= esc_html($label);
        $html .= '</label>';
        return $html;
    }

    protected function is_blocks_checkout(): bool {
        $is_blocks = function_exists('is_cart') && function_exists('is_checkout') && !is_cart() && !is_checkout() && function_exists('wc_is_active_theme') && wc_is_active_theme('block-based');
        if (function_exists('has_block') && has_block('woocommerce/checkout')) {
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