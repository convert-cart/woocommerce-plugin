/**
 * External dependencies
 */
/* eslint-disable react/react-in-jsx-scope */
import { Icon, megaphone } from '@wordpress/icons';
import {
  registerBlockType,
  getCategories,
  setCategories,
} from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import { Edit, Save } from './edit';
import { smsConsentAttributes } from './attributes';
import metadata from './block.json';

const categories = getCategories();
setCategories([...categories, { slug: 'convertcart', title: 'ConvertCart' }]);

// Attributes are in metadata vs. settings are used strangely here.
// Needs more investigation to enable correct types.
/* eslint-disable @typescript-eslint/no-explicit-any,@typescript-eslint/no-unsafe-argument */
registerBlockType(
  metadata,
  {
    icon: {
      src: <Icon icon={megaphone} />,
      foreground: '#7f54b3',
    },
    attributes: {
      ...metadata.attributes,
      ...smsConsentAttributes,
    },
    edit: Edit,
    save: Save,
  },
);
