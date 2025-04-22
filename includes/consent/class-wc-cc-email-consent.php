<?php
declare(strict_types=1);

namespace ConvertCart\Analytics\Consent;

use WC_Integration;

/**
 * Email consent handling.
 */
class WC_CC_Email_Consent extends WC_CC_Consent_Base {
    protected string $consent_type = 'email';
    protected string $option_name = 'enable_email_consent';
    protected string $meta_key = '_convertcart_email_consent';
    protected string $checkbox_name = 'email_consent';

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
        parent::__construct($integration, 'email', $plugin_url, $plugin_path, $plugin_version);
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
            echo '<div class="consent-checkbox email-consent-checkbox">';
            echo $checkbox_html;
            echo '</div>';
        }
    }

    public function enqueue_scripts(): void {
        // Remove this method or ensure it's not being called
        return;
    }

    protected function generate_checkbox_html(): string {
        $label = __('I consent to receive email communications.', 'woocommerce_cc_analytics');
        $html = '<label for="' . esc_attr($this->checkbox_name) . '">';
        $html .= '<input type="checkbox" name="' . esc_attr($this->checkbox_name) . '" id="' . esc_attr($this->checkbox_name) . '" value="yes" />';
        $html .= esc_html($label);
        $html .= '</label>';
        return $html;
    }

    public function get_default_consent_html(): string {
        return '<p class="form-row">
            <label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
                <input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" 
                       name="email_consent" id="email_consent" value="yes" />
                <span>' . esc_html__('I agree to receive marketing emails about new products and special offers.', 'woocommerce_cc_analytics') . '</span>
            </label>
        </p>';
    }
} 