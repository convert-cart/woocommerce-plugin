<?php
declare(strict_types=1);

namespace ConvertCart\Analytics\Consent;

use WC_Integration;

/**
 * SMS consent handling.
 */
class WC_CC_SMS_Consent extends WC_CC_Consent_Base {
    /**
     * Constructor.
     *
     * @param WC_Integration $integration
     */
    public function __construct(WC_Integration $integration) {
        parent::__construct($integration, 'sms');
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