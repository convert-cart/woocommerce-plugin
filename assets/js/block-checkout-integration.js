/**
 * Convert Cart Analytics - WooCommerce Checkout Integration
 * Handles both Classic and Block-based checkout scenarios
 */
// Ensure wp and wc objects are available if needed later (though primarily using vanilla JS now)
// import { registerCheckoutBlock } from '@woocommerce/blocks-registry'; // Keep if block-specific features are added back
// import { useEffect } from '@wordpress/element'; // Keep if block-specific features are added back

// Debug helper
const debug = (msg, data = null) => {
    console.log(`ConvertCart Debug: ${msg}`, data !== null ? data : '');
};

debug('Script loaded'); // Log script load

// Utility functions
const isBlockCheckout = () => {
    return document.querySelector('.wc-block-checkout') !== null;
};

const isClassicCheckout = () => {
    return document.querySelector('form.checkout') !== null && !document.querySelector('.wc-block-checkout');
};

// Initialize based on checkout type
const initializeCheckout = () => {
    const consentData = window.convertcart_consent_data || {};
    console.log('ConvertCart consent data:', consentData); // Temporary debug log

    if (typeof consentData.sms_enabled === 'undefined' && typeof consentData.email_enabled === 'undefined') {
        return;
    }

    if (!consentData.sms_enabled && !consentData.email_enabled) {
        return;
    }

    if (isBlockCheckout()) {
        initializeBlockCheckout(consentData);
    } else if (isClassicCheckout()) {
        initializeClassicCheckout(consentData);
    } else {
        observeForCheckout(consentData);
    }
};

// Fallback observer if initial detection fails
const observeForCheckout = (consentData) => {
    const observer = new MutationObserver((mutations, observer) => {
        if (isBlockCheckout()) {
            observer.disconnect();
            initializeBlockCheckout(consentData);
        } else if (isClassicCheckout()) {
            observer.disconnect();
            initializeClassicCheckout(consentData);
        }
    });

    observer.observe(document.body, { childList: true, subtree: true });
    setTimeout(() => observer.disconnect(), 15000);
};

// Block checkout initialization
const initializeBlockCheckout = (consentData) => {
    const insertBlockCheckboxes = () => {
        if (document.querySelector('.cc-consent-wrapper')) {
            return true;
        }

        const insertionPoints = [
            '.wc-block-checkout__terms',
            '.wc-block-components-checkout-place-order-button',
            '.wc-block-checkout__actions',
            '.wc-block-components-checkout-step__content'
        ];

        for (const selector of insertionPoints) {
            const container = document.querySelector(selector);
            if (container) {
                const wrapper = document.createElement('div');
                wrapper.className = 'cc-consent-wrapper';
                let inserted = false;

                if (consentData.sms_enabled && consentData.sms_consent_html) {
                    const smsElement = createWooCheckbox(consentData.sms_consent_html, 'sms');
                    wrapper.appendChild(smsElement);
                    inserted = true;
                }

                if (consentData.email_enabled && consentData.email_consent_html) {
                    const emailElement = createWooCheckbox(consentData.email_consent_html, 'email');
                    wrapper.appendChild(emailElement);
                    inserted = true;
                }

                if (inserted) {
                    container.parentNode.insertBefore(wrapper, container);
                    addConsentHandlers(wrapper);
                    return true;
                }
                return false;
            }
        }
        return false;
    };

    if (!insertBlockCheckboxes()) {
        const observer = new MutationObserver((mutations, observer) => {
            if (insertBlockCheckboxes()) {
                observer.disconnect();
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        setTimeout(() => observer.disconnect(), 15000);
    }
};

// Classic checkout initialization
const initializeClassicCheckout = (consentData) => {
    const insertClassicCheckboxes = () => {
        if (document.querySelector('.cc-consent-wrapper')) {
            return true;
        }

        const form = document.querySelector('form.checkout');
        if (!form) {
            return false;
        }

        const insertionPoints = [
            consentData.insertion_point || '.woocommerce-terms-and-conditions-wrapper',
            '#payment .place-order',
            '.woocommerce-checkout-payment',
            '#order_review'
        ];

        for (const selector of insertionPoints) {
            const container = form.querySelector(selector);
            if (container) {
                const wrapper = document.createElement('div');
                wrapper.className = 'cc-consent-wrapper';
                let inserted = false;

                if (consentData.sms_enabled && consentData.sms_consent_html) {
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = consentData.sms_consent_html.trim();
                    const smsNode = tempDiv.firstChild;
                    if (smsNode) {
                        wrapper.appendChild(smsNode);
                        inserted = true;
                    }
                }

                if (consentData.email_enabled && consentData.email_consent_html) {
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = consentData.email_consent_html.trim();
                    const emailNode = tempDiv.firstChild;
                    if (emailNode) {
                        wrapper.appendChild(emailNode);
                        inserted = true;
                    }
                }

                if (inserted) {
                    container.parentNode.insertBefore(wrapper, container);
                    addConsentHandlers(wrapper);
                    return true;
                }
                return false;
            }
        }
        return false;
    };

    if (!insertClassicCheckboxes()) {
        const observer = new MutationObserver((mutations, observer) => {
            if (insertClassicCheckboxes()) {
                observer.disconnect();
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        setTimeout(() => observer.disconnect(), 15000);
    }
};

// Add event handlers for consent checkboxes
const addConsentHandlers = (container) => {
    container.addEventListener('change', (event) => {
        if (!event.target.matches('input[type="checkbox"]')) return;

        const inputName = event.target.name;
        if (inputName === 'sms_consent' || inputName === 'email_consent') {
            const value = event.target.checked ? 'yes' : 'no';

            if (window.wc?.blocksCheckout?.dispatch?.setExtensionData) {
                window.wc.blocksCheckout.dispatch.setExtensionData(
                    'convertcart-analytics',
                    inputName,
                    value
                );
            }

            if (typeof jQuery !== 'undefined') {
                jQuery(document.body).trigger('update_checkout');
            }
        }
    });
};

// Helper function to create checkbox container
const createWooCheckbox = (html, type) => {
    const container = document.createElement('div');
    container.className = `cc-consent-item cc-consent-${type}`;
    container.innerHTML = html;
    return container;
};

// Initialize on DOM ready or interactive
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => setTimeout(initializeCheckout, 100));
} else {
    setTimeout(initializeCheckout, 100);
} 