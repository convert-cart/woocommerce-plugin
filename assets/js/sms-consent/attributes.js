/**
 * SMS Consent Block Attributes
 *
 * @package ConvertCart\Analytics
 */

import { getSetting } from '@woocommerce/settings';

const { defaultText } = getSetting(
	'convertcart',
	{
		defaultText: 'I consent to SMS communications.'
	}
);

export const smsConsentAttributes = {
	text: {
		type: 'string',
		default: defaultText,
			},
			};
