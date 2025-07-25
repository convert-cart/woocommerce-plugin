/**
 * Email Consent Block Frontend
 *
 * @package ConvertCart\Analytics
 */

import { registerCheckoutBlock } from '@woocommerce/blocks-checkout';

/**
 * Internal dependencies
 */
import metadata from './block.json'; // eslint-disable-line import/no-duplicates -- ESLint detects these two as duplicates of each other.
import { FrontendBlock } from './block.jsx'; // eslint-disable-line import/no-duplicates -- ESLint detects these two as duplicates of each other.

registerCheckoutBlock(
	{
		metadata,
		component: FrontendBlock,
	}
);
