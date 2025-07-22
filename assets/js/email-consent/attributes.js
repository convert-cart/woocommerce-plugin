/**
 * Email Consent Block Attributes
 *
 * @package ConvertCart\Analytics
 */

import { getSetting } from '@woocommerce/settings';

const { defaultText } = getSetting(
	'convertcart',
	{
		defaultText: 'I consent to Email communications.'
	}
);

export const emailConsentAttributes = {
	text: {
		type: 'string',
		default: defaultText,
			},
			};
