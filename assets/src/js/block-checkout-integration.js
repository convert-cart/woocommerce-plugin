/**
 * Convert Cart Analytics - WooCommerce Checkout Integration
 * Handles Block-based checkout using the Extensibility API.
 */
import { __ } from '@wordpress/i18n';
import { CheckboxControl } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { ConsentCheckboxes } from './components/ConsentCheckboxes';

console.log('[ConvertCart DEBUG] Script loading...');

// Wait for WC settings to be available
const waitForWCSettings = (callback, maxAttempts = 10) => {
    let attempts = 0;
    
    const check = () => {
        attempts++;
        console.log(`[ConvertCart DEBUG] Checking for WC settings (attempt ${attempts})`);
        
        if (window.wc?.settings?.getSetting) {
            console.log('[ConvertCart DEBUG] WC settings found');
            callback();
            return;
        }
        
        if (attempts >= maxAttempts) {
            console.error('[ConvertCart ERROR] Failed to find WC settings after', maxAttempts, 'attempts');
            return;
        }
        
        setTimeout(check, 100);
    };
    
    check();
};

// Initialize only after WC settings are available
waitForWCSettings(() => {
    const NAMESPACE = 'convertcart-analytics';
    const getSetting = window.wc.settings.getSetting;
    
    // Get data with fallback
    const scriptData = getSetting ? getSetting(NAMESPACE + '_data', {}) : {};
    console.log('[ConvertCart DEBUG] Script data:', scriptData);
    
    // Component definition
    const ConvertCartConsentCheckboxes = () => {
        console.log('[ConvertCart DEBUG] ConsentCheckboxes component rendering');
        console.log('[ConvertCart DEBUG] Script data:', scriptData);

        const { sms_enabled, email_enabled, sms_consent_html, email_consent_html } = scriptData;
        console.log('[ConvertCart DEBUG] Enabled states - SMS:', sms_enabled, 'Email:', email_enabled);

        // Initialize state based on localized data, default to false if not enabled/present
        const [smsChecked, setSmsChecked] = useState(false);
        const [emailChecked, setEmailChecked] = useState(false);
        const { setExtensionData } = useDispatch('wc/store/checkout');

        // Log initial state values
        console.log('[ConvertCart Blocks DEBUG] Initial component state: smsChecked=', smsChecked, 'emailChecked=', emailChecked);

        // Extract labels from HTML (memoize or do this outside if performance is critical)
        const extractLabel = (html) => {
            if (!html) return '';
            try {
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = html;
                const labelText = tempDiv.querySelector('label span')?.textContent?.trim() || '';
                // console.log('[ConvertCart Blocks DEBUG] Extracted label:', labelText, 'from HTML:', html); // Can be verbose
                return labelText;
            } catch (e) {
                console.error('[ConvertCart Blocks ERROR] Error extracting label:', e);
                return '';
            }
        };

        // Get labels safely
        const smsLabel = extractLabel(sms_consent_html);
        const emailLabel = extractLabel(email_consent_html);
        console.log('[ConvertCart Blocks DEBUG] Labels - SMS:', smsLabel, 'Email:', emailLabel);
        console.log('[ConvertCart Blocks DEBUG] Consent enabled from scriptData - SMS:', sms_enabled, 'Email:', email_enabled);

        // Effect to log when setExtensionData changes (useful for debugging context issues)
        useEffect(() => {
            console.log('[ConvertCart Blocks DEBUG] setExtensionData function reference updated (or component re-rendered).');
        }, [setExtensionData]);

        // Render only if at least one checkbox is enabled
        if (!sms_enabled && !email_enabled) {
            console.log('[ConvertCart Blocks DEBUG] No consent types enabled in scriptData. Rendering null.');
            return null;
        }

        console.log('[ConvertCart Blocks DEBUG] Rendering CheckboxControls.');
        return (
            <>
                {sms_enabled && (
                    <CheckboxControl
                        className="wc-block-checkout__sms-consent convertcart-consent-checkbox"
                        label={smsLabel || __('SMS Consent', 'convertcart-analytics')}
                        checked={smsChecked}
                        onChange={(isChecked) => {
                            console.log('[ConvertCart Blocks DEBUG] SMS checkbox changed:', isChecked);
                            setSmsChecked(isChecked);
                            if (setExtensionData) {
                                setExtensionData(NAMESPACE, 'sms_consent', isChecked ? 'yes' : 'no');
                                console.log('[ConvertCart Blocks DEBUG] Called setExtensionData for SMS:', isChecked ? 'yes' : 'no');
                            } else {
                                console.error('[ConvertCart Blocks ERROR] setExtensionData is not available!');
                            }
                        }}
                        style={{ marginBottom: '0.5em' }}
                    />
                )}
                {email_enabled && (
                    <CheckboxControl
                        className="wc-block-checkout__email-consent convertcart-consent-checkbox"
                        label={emailLabel || __('Email Consent', 'convertcart-analytics')}
                        checked={emailChecked}
                        onChange={(isChecked) => {
                            console.log('[ConvertCart Blocks DEBUG] Email checkbox changed:', isChecked);
                            setEmailChecked(isChecked);
                             if (setExtensionData) {
                                setExtensionData(NAMESPACE, 'email_consent', isChecked ? 'yes' : 'no');
                                console.log('[ConvertCart Blocks DEBUG] Called setExtensionData for Email:', isChecked ? 'yes' : 'no');
                            } else {
                                console.error('[ConvertCart Blocks ERROR] setExtensionData is not available!');
                            }
                        }}
                        style={{ marginBottom: '0.5em' }}
                    />
                )}
            </>
        );
    };

    // Block registration
    const initialize = () => {
        if (!window?.wc?.blocksCheckout?.registerCheckoutBlock) {
            console.warn('[ConvertCart DEBUG] WC Blocks API not found. Will retry on window load...');
            return;
        }

        try {
            console.log('[ConvertCart DEBUG] Attempting to register block...');
            window.wc.blocksCheckout.registerCheckoutBlock({
                metadata: {
                    name: 'convertcart/consent',
                    parent: ['woocommerce/checkout-contact-information-block'],
                },
                component: ConvertCartConsentCheckboxes
            });
            console.log('[ConvertCart DEBUG] Block registration successful');
        } catch (error) {
            console.error('[ConvertCart DEBUG] Block registration failed:', error);
        }
    };

    // Initialize when ready
    if (document.readyState === 'complete') {
        initialize();
    } else {
        window.addEventListener('load', initialize);
    }
}); 