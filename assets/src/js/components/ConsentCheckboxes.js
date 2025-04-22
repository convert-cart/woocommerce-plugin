import { __ } from '@wordpress/i18n';
import { CheckboxControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';

// Get data passed via get_script_data() in PHP
const scriptData = window.wc?.settings?.getSetting?.('convertcart-analytics_data', {}) || {};

export const ConsentCheckboxes = () => {
    console.log('[ConvertCart DEBUG] ConsentCheckboxes component rendering');
    console.log('[ConvertCart DEBUG] Script data:', scriptData);

    const { sms_enabled, email_enabled, sms_consent_html, email_consent_html } = scriptData;
    console.log('[ConvertCart DEBUG] Enabled states - SMS:', sms_enabled, 'Email:', email_enabled);
    const [smsChecked, setSmsChecked] = useState(false);
    const [emailChecked, setEmailChecked] = useState(false);
    const { setExtensionData } = useDispatch('wc/store/checkout');

    // Extract labels from HTML
    const extractLabel = (html) => {
        if (!html) return '';
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = html;
        return tempDiv.querySelector('label span')?.textContent?.trim() || '';
    };

    const smsLabel = extractLabel(sms_consent_html);
    const emailLabel = extractLabel(email_consent_html);

    if (!sms_enabled && !email_enabled) {
        return null;
    }

    return (
        <>
            {sms_enabled && (
                <CheckboxControl
                    className="wc-block-checkout__sms-consent convertcart-consent-checkbox"
                    label={smsLabel || __('SMS Consent', 'convertcart-analytics')}
                    checked={smsChecked}
                    onChange={(isChecked) => {
                        setSmsChecked(isChecked);
                        setExtensionData('convertcart-analytics', 'sms_consent', isChecked ? 'yes' : 'no');
                    }}
                />
            )}
            {email_enabled && (
                <CheckboxControl
                    className="wc-block-checkout__email-consent convertcart-consent-checkbox"
                    label={emailLabel || __('Email Consent', 'convertcart-analytics')}
                    checked={emailChecked}
                    onChange={(isChecked) => {
                        setEmailChecked(isChecked);
                        setExtensionData('convertcart-analytics', 'email_consent', isChecked ? 'yes' : 'no');
                    }}
                />
            )}
        </>
    );
}; 