/**
 * Convert Cart Analytics - WooCommerce Checkout Integration
 * Handles Block-based checkout using the Extensibility API.
 * Classic checkout logic might need to be separated or conditionally run.
 */
import { __ } from '@wordpress/i18n';
import { registerCheckoutBlockExtension } from '@woocommerce/blocks-checkout';
import { CheckboxControl } from '@wordpress/components';
import { useState, useEffect, useCallback } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { CHECKOUT_STORE_KEY } from '@woocommerce/block-data';
import { decodeEntities } from '@wordpress/html-entities';

// --- Configuration ---
// Get localized data (must be available globally)
const consentData = window.convertcart_consent_data || {};
const NAMESPACE = consentData.namespace || 'convertcart-analytics'; // Our integration namespace

// --- React Component for Consent Checkboxes ---
const ConvertCartConsentCheckboxes = ({ extensionData, setExtensionData }) => {
    // Get initial state from extension data if available, otherwise default based on enabled status
    const initialSmsConsent = extensionData?.sms_consent === 'yes';
    const initialEmailConsent = extensionData?.email_consent === 'yes';

    // Use local state to control the checkboxes
    const [smsChecked, setSmsChecked] = useState(initialSmsConsent);
    const [emailChecked, setEmailChecked] = useState(initialEmailConsent);

    // Function to update extension data when checkbox changes
    const updateConsent = useCallback((type, isChecked) => {
        const value = isChecked ? 'yes' : 'no';
        console.log(`[ConvertCart Blocks] Updating ${type} consent to: ${value}`);
        setExtensionData(NAMESPACE, `${type}_consent`, value);
    }, [setExtensionData]);

    // Handle SMS checkbox change
    const handleSmsChange = (isChecked) => {
        setSmsChecked(isChecked);
        updateConsent('sms', isChecked);
    };

    // Handle Email checkbox change
    const handleEmailChange = (isChecked) => {
        setEmailChecked(isChecked);
        updateConsent('email', isChecked);
    };

    // Render nothing if neither consent type is enabled
    if (!consentData.sms_enabled && !consentData.email_enabled) {
        return null;
    }

    console.log('[ConvertCart Blocks] Rendering Consent Checkboxes Component');

    return (
        <div className="cc-consent-wrapper-blocks" style={{ marginBottom: '1em' }}>
            {consentData.sms_enabled && consentData.sms_consent_html && (
                <div className="cc-consent-item cc-consent-sms">
                    {/* Use CheckboxControl for better accessibility and integration */}
                    {/* We need to extract the label text from the HTML */}
                    {/* Option 1: Simple CheckboxControl (loses custom HTML structure) */}
                    {/* <CheckboxControl
                        label={ decodeEntities(consentData.sms_consent_html.replace(/<[^>]*>/g, '')) || __('SMS Consent', 'woocommerce_cc_analytics') } // Basic label extraction
                        checked={ smsChecked }
                        onChange={ handleSmsChange }
                        name="sms_consent"
                    /> */}

                    {/* Option 2: Render provided HTML and attach CheckboxControl logic */}
                    {/* This is tricky because CheckboxControl expects label prop */}
                    {/* Let's try rendering the HTML and managing state separately */}
                    <div dangerouslySetInnerHTML={{ __html: consentData.sms_consent_html }} />
                    {/* Hidden CheckboxControl to manage state? Or find input in rendered HTML? */}
                    {/* Let's try finding the input within the rendered HTML */}
                    {/* This requires useEffect to find the input after render */}

                </div>
            )}
             {consentData.email_enabled && consentData.email_consent_html && (
                <div className="cc-consent-item cc-consent-email">
                     <div dangerouslySetInnerHTML={{ __html: consentData.email_consent_html }} />
                </div>
            )}
            {/* We need to add event listeners to the inputs inside the dangerouslySetInnerHTML */}
            {/* This is generally discouraged in React, but necessary if we must use the exact HTML */}
            {/* Let's refine this part */}
        </div>
    );
};


// --- Refined Component using CheckboxControl and extracting label ---
const ConvertCartConsentCheckboxesRefined = () => {
    const { setExtensionData } = useDispatch(CHECKOUT_STORE_KEY);
    const extensionData = useSelect((select) => {
        const store = select(CHECKOUT_STORE_KEY);
        return store.getExtensionData()?.[NAMESPACE] || {};
    }, []);


    // Get initial state from extension data if available, otherwise default based on enabled status
    const initialSmsConsent = extensionData?.sms_consent === 'yes';
    const initialEmailConsent = extensionData?.email_consent === 'yes';

    // Use local state to control the checkboxes
    const [smsChecked, setSmsChecked] = useState(initialSmsConsent);
    const [emailChecked, setEmailChecked] = useState(initialEmailConsent);

     // Function to update extension data when checkbox changes
    const updateConsent = useCallback((type, isChecked) => {
        const value = isChecked ? 'yes' : 'no';
        console.log(`[ConvertCart Blocks] Updating ${type} consent to: ${value}`);
        // Ensure the structure matches what the PHP expects: { 'convertcart-analytics': { sms_consent: 'yes', ... } }
        setExtensionData(NAMESPACE, type === 'sms' ? 'sms_consent' : 'email_consent', value);
    }, [setExtensionData]);


    // Handle SMS checkbox change
    const handleSmsChange = (isChecked) => {
        setSmsChecked(isChecked);
        updateConsent('sms', isChecked);
    };

    // Handle Email checkbox change
    const handleEmailChange = (isChecked) => {
        setEmailChecked(isChecked);
        updateConsent('email', isChecked);
    };

    // Helper to extract label text from HTML (basic version)
    const extractLabel = (html) => {
        if (!html) return '';
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = html;
        const labelElement = tempDiv.querySelector('label');
        // Remove the input from the label text if it's inside
        const inputElement = labelElement ? labelElement.querySelector('input') : null;
        if (inputElement) {
            inputElement.remove();
        }
        return decodeEntities(labelElement?.textContent?.trim() || '');
    };

    const smsLabel = extractLabel(consentData.sms_consent_html) || __('SMS Consent Required', 'woocommerce_cc_analytics'); // Provide default
    const emailLabel = extractLabel(consentData.email_consent_html) || __('Email Consent Required', 'woocommerce_cc_analytics'); // Provide default


    // Render nothing if neither consent type is enabled
    if (!consentData.sms_enabled && !consentData.email_enabled) {
        console.log('[ConvertCart Blocks] No consent enabled.');
        return null;
    }

    console.log('[ConvertCart Blocks] Rendering Refined Consent Checkboxes Component');

    return (
        <div className="cc-consent-wrapper-blocks" style={{ marginBottom: '1em', marginTop: '1em', padding: '1em', border: '1px solid #e0e0e0', borderRadius: '4px' }}>
            <h4>{__('Marketing Consent', 'woocommerce_cc_analytics')}</h4>
            {consentData.sms_enabled && (
                 <CheckboxControl
                    className="cc-consent-item cc-consent-sms"
                    label={ smsLabel }
                    checked={ smsChecked }
                    onChange={ handleSmsChange }
                    name="sms_consent" // Name might not be strictly needed here
                />
            )}
             {consentData.email_enabled && (
                 <CheckboxControl
                    className="cc-consent-item cc-consent-email"
                    label={ emailLabel }
                    checked={ emailChecked }
                    onChange={ handleEmailChange }
                    name="email_consent" // Name might not be strictly needed here
                />
            )}
        </div>
    );
};


// --- Register the Extension ---
try {
    console.log('[ConvertCart Blocks] Attempting to register Checkout Block Extension.');
    registerCheckoutBlockExtension({
        namespace: NAMESPACE,
        // Render the component
        render: () => <ConvertCartConsentCheckboxesRefined />,
        // No validation logic here yet, can be added later
    });
    console.log('[ConvertCart Blocks] Checkout Block Extension registered successfully.');
} catch (error) {
     console.error('[ConvertCart Blocks] Error registering checkout block extension:', error);
}


// --- Classic Checkout Logic (Keep separate or remove if not needed) ---
// const isClassicCheckout = () => { ... };
// const initializeClassicCheckout = (consentData) => { ... };
// const observeForCheckout = (consentData) => { ... };

// // Conditional initialization based on checkout type
// const initializeCheckout = () => {
//     if (document.querySelector('.wc-block-checkout')) {
//         // Block checkout is handled by the registration above
//         console.log('[ConvertCart] Block checkout detected, extension handles rendering.');
//     } else if (isClassicCheckout()) {
//         console.log('[ConvertCart] Classic checkout detected.');
//         // initializeClassicCheckout(consentData); // Call classic init if needed
//     } else {
//         console.log('[ConvertCart] No checkout form detected initially.');
//         // observeForCheckout(consentData); // Fallback observer if needed
//     }
// };

// // Run initialization logic
// if (document.readyState === 'loading') {
//     document.addEventListener('DOMContentLoaded', initializeCheckout);
// } else {
//     initializeCheckout();
// } 