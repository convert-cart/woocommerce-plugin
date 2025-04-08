<?php
declare(strict_types=1);

namespace ConvertCart\Analytics\Consent;

use WC_Integration;

/**
 * Email consent handling.
 */
class WC_CC_Email_Consent extends WC_CC_Consent_Base {
    /**
     * Constructor.
     *
     * @param WC_Integration $integration
     */
    public function __construct(WC_Integration $integration) {
        parent::__construct($integration, 'email');
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
                       name="email_consent" id="email_consent" value="yes" />
                <span>' . esc_html__('I agree to receive marketing emails about new products and special offers.', 'woocommerce_cc_analytics') . '</span>
            </label>
        </p>';
    }
} 