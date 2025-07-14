import { CheckboxControl } from '@woocommerce/blocks-checkout';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { getSetting } from '@woocommerce/settings';

// Get settings with fallback values
const settings = getSetting('convertcart-email-consent_data', {
    trackingEnabled: false,
    defaultText: 'I consent to Email communications.'
});

if (typeof window !== 'undefined' && window.console) {
	console.log('ConvertCart: Email Consent Block settings loaded', { 
        settings,
        allAvailableSettings: Object.keys(getSetting('') || {})
    });
}

const { 
    defaultText = 'I consent to Email communications.', 
    trackingEnabled = false,
    consent = false
} = settings;

// Debug log settings
if (typeof window !== 'undefined' && window.console) {
	console.log('ConvertCart: Email Consent Block settings loaded', { settings, defaultText, trackingEnabled });
}

export function EmailConsentBlock({ checkoutExtensionData }) {
	const [isChecked, setIsChecked] = useState(consent);
	const { setExtensionData } = checkoutExtensionData || {};
	
	useEffect(() => {
		if (setExtensionData) {
			setExtensionData("convertcart", "email_consent", isChecked);
			
			// Debug log
			if (typeof window !== 'undefined' && window.console) {
				console.log('ConvertCart: Setting Email consent data', isChecked);
			}
		} else {
            // Debug log
            if (typeof window !== 'undefined' && window.console) {
                console.warn('ConvertCart: setExtensionData is not available in checkoutExtensionData');
            }
        }
	}, [isChecked, setExtensionData]);

    // Debug log render attempt
    if (typeof window !== 'undefined' && window.console) {
        console.log('ConvertCart: Email Consent Block render attempt', { trackingEnabled, defaultText });
    }

    if (!trackingEnabled) {
        if (typeof window !== 'undefined' && window.console) {
            console.log('ConvertCart: Email Consent Block not rendered - tracking disabled');
        }
        return null;
    }

	return (
		<CheckboxControl
			label={__(defaultText, 'convertcart')}
			checked={isChecked}
			onChange={setIsChecked}
		/>
	);
}

export const FrontendBlock = EmailConsentBlock;
