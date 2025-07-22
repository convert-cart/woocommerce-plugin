import { CheckboxControl } from '@woocommerce/blocks-checkout';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { getSetting } from '@woocommerce/settings';

// Get settings with fallback values
const settings = getSetting('convertcart-email-consent_data', {
    trackingEnabled: false,
    defaultText: 'I consent to Email communications.'
});


const { 
    defaultText = 'I consent to Email communications.', 
    trackingEnabled = false,
    consent = false
} = settings;


export function EmailConsentBlock({ checkoutExtensionData }) {
	const [isChecked, setIsChecked] = useState(consent);
	const { setExtensionData } = checkoutExtensionData || {};
	
	useEffect(() => {
		if (setExtensionData) {
			setExtensionData("convertcart", "email_consent", isChecked);
			
		} else {
        }
	}, [isChecked, setExtensionData]);


    if (!trackingEnabled) {
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
