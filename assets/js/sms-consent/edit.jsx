/* eslint-disable react/react-in-jsx-scope */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText } from '@wordpress/block-editor';
import { CheckboxControl } from '@wordpress/components';
import { getSetting } from '@woocommerce/settings';
import { Placeholder, Button } from '@wordpress/components';
import { Icon, megaphone } from '@wordpress/icons';

const adminUrl = getSetting('adminUrl', '');
const settings = getSetting('convertcart-sms-consent_data', {
    trackingEnabled: false,
    defaultText: 'I consent to SMS communications.',
    consent: false
});


const { 
    trackingEnabled = false, 
    defaultText = 'I consent to SMS communications.',
    consent = false
} = settings;

function EmptyState() {
  const adminUrlToEnableOptIn = `${adminUrl}admin.php?page=wc-settings&tab=integration&section=cc_analytics`;
  return (
    <Placeholder
      icon={<Icon icon={megaphone} />}
      label={__('ConvertCart SMS Consent', 'convertcart')}
      className="wp-block-convertcart-sms-consent-placeholder"
    >
      <span className="wp-block-convertcart-sms-consent-placeholder__description">
        {__(
          'ConvertCart SMS consent would be shown here if enabled. You can enable from the settings page.',
          'convertcart',
        )}
      </span>
      <Button
        variant="primary"
        className="wp-block-convertcart-sms-consent-placeholder__button"
        onClick={() => {
          window.open(adminUrlToEnableOptIn, '_blank', 'noopener noreferrer');
        }}
      >
        {__('Enable ConvertCart SMS Consent', 'convertcart')}
      </Button>
    </Placeholder>
  );
}

export function Edit({
  attributes: { text },
  setAttributes
}) {
  const blockProps = useBlockProps();
  const currentText = text || defaultText;
  return (
    <div {...blockProps}>
      {trackingEnabled ? (
        <div className="wc-block-checkout__sms-consent">
          <CheckboxControl
            label={currentText}
            checked={false}
            onChange={() => {
                // Placeholder for future functionality
                // This can be used to handle consent state changes
                // Currently, this is just a placeholder as the actual consent handling
                // logic would be implemented in the frontend block.
                // For now, we are not storing any state here.
            }}
          />
          <RichText
            value={currentText}
            onChange={(value) => setAttributes({ text: value })}
            placeholder={__('Enter SMS consent text...', 'convertcart')}
          />
        </div>
      ) : (
        <EmptyState />
      )}
    </div>
  );
}

export function Save() {
  return <div {...useBlockProps.save()} />;
}
