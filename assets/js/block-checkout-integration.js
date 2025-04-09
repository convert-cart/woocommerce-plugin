/**
 * Convert Cart Analytics - WooCommerce Blocks Checkout Integration
 * Attempts standard WooCommerce styling first, falls back to direct DOM insertion
 */
(function (wp, wc) {
    if (!wp?.element || !wp?.htmlEntities) {
        console.error('Convert Cart: Missing required WP dependencies.', { wp });
        return;
    }

    // Get consent HTML from localized data
    const smsData = window.convertcart_consent_data_sms || {};
    const emailData = window.convertcart_consent_data_email || {};
    const smsConsentHtml = smsData.sms_consent_html || '';
    const emailConsentHtml = emailData.email_consent_html || '';

    if (!smsConsentHtml && !emailConsentHtml) {
        return;
    }

    // Handle checkbox state changes
    const handleCheckboxChange = (inputName, isChecked) => {
        if (wc?.blocksCheckout?.dispatch?.setExtensionData) {
            wc.blocksCheckout.dispatch.setExtensionData(
                'convertcart-analytics',
                inputName,
                isChecked ? 'yes' : 'no'
            );
        }
    };

    // Function to create WooCommerce-styled checkbox container
    const createWooCheckbox = (html, type) => {
        // Create the main container
        const container = document.createElement('div');
        container.className = `wc-block-components-checkout-field wc-block-checkout__${type}-consent`;

        // Create the checkbox wrapper
        const checkboxWrapper = document.createElement('div');
        checkboxWrapper.className = 'wc-block-components-checkbox';

        // Create the label
        const label = document.createElement('label');
        label.className = 'wc-block-components-checkbox__label';

        // Create the input wrapper
        const inputWrapper = document.createElement('span');
        inputWrapper.className = 'wc-block-components-checkbox__input-wrapper';

        // Create the checkbox input
        const input = document.createElement('input');
        input.type = 'checkbox';
        input.name = `${type}_consent`;
        input.id = `${type}_consent`;
        input.className = 'wc-block-components-checkbox__input';

        // Create the text span
        const textSpan = document.createElement('span');
        textSpan.className = 'wc-block-components-checkbox__text';
        textSpan.textContent = type === 'sms' ? 
            'I consent to receive SMS communications Checkout.' :
            'I consent to receive email communications.';

        // Assemble the elements
        inputWrapper.appendChild(input);
        label.appendChild(inputWrapper);
        label.appendChild(textSpan);
        checkboxWrapper.appendChild(label);
        container.appendChild(checkboxWrapper);

        return container;
    };

    // Function to insert checkboxes using WooCommerce's structure
    const insertConsentCheckboxes = () => {
        // Look for the terms container first (preferred location)
        const termsContainer = document.querySelector('.wc-block-checkout__terms');
        if (!termsContainer) {
            console.log('Convert Cart: Terms container not found, will try fallback.');
            return false;
        }

        // Check if already inserted
        if (document.querySelector('.wc-block-checkout__sms-consent') || 
            document.querySelector('.wc-block-checkout__email-consent')) {
            return true;
        }

        console.log('Convert Cart: Inserting checkboxes with WooCommerce styling.');

        // Create containers with WooCommerce styling
        if (smsConsentHtml) {
            const smsContainer = createWooCheckbox(smsConsentHtml, 'sms');
            termsContainer.insertBefore(smsContainer, termsContainer.firstChild);
        }

        if (emailConsentHtml) {
            const emailContainer = createWooCheckbox(emailConsentHtml, 'email');
            termsContainer.insertBefore(emailContainer, termsContainer.firstChild);
        }

        // Add event listeners
        termsContainer.addEventListener('change', (event) => {
            if (event.target.matches('input[type="checkbox"]')) {
                const inputName = event.target.name;
                if (inputName === 'sms_consent' || inputName === 'email_consent') {
                    handleCheckboxChange(inputName, event.target.checked);
                }
            }
        });

        console.log('Convert Cart: Checkboxes inserted with WooCommerce styling.');
        return true;
    };

    // Set up observer to wait for the terms container
    const targetNode = document.querySelector('.wc-block-checkout') || document.body;
    if (targetNode) {
        const observer = new MutationObserver((mutationsList, observer) => {
            if (insertConsentCheckboxes()) {
                console.log('Convert Cart: Successfully inserted checkboxes, disconnecting observer.');
                observer.disconnect();
            }
        });

        // Try immediate insertion first
        if (!insertConsentCheckboxes()) {
            console.log('Convert Cart: Initial insertion failed, starting observer.');
            observer.observe(targetNode, { childList: true, subtree: true });

            // Disconnect after timeout
            setTimeout(() => {
                observer.disconnect();
                console.log('Convert Cart: Observer timed out.');
            }, 15000);
        }
    }

})(window.wp, window.wc); 