/* global jQuery, _, wp, ccAdminSettings */
jQuery(document).ready(function ($) {
	// Ensure CodeMirror settings are available
	if (typeof wp === 'undefined' || typeof wp.codeEditor === 'undefined' || typeof ccAdminSettings === 'undefined' || typeof ccAdminSettings.codeEditorSettings === 'undefined') {
		console.error('CodeMirror settings not available for CC Analytics.');
		return;
	}

	// Initialize CodeMirror for each textarea with the class 'codemirror-textarea'
	$('.codemirror-textarea').each(function () {
		var $textarea = $(this);
		// Use settings passed via wp_localize_script
		var editorSettings = ccAdminSettings.codeEditorSettings;

		// Customize settings if needed (example from original code)
		editorSettings.codemirror = _.extend(
			{},
			editorSettings.codemirror,
			{
				mode: 'htmlmixed',
				indentUnit: 2,
				tabSize: 2,
				lineNumbers: true,
				theme: 'default',
				// You might need additional linting setup if required
				// lint: { "indentation": "tabs" } // Example
			}
		);

		// Initialize the editor
		var editor = wp.codeEditor.initialize($textarea, editorSettings);

		// Refresh editor when it becomes visible if needed (e.g., in tabs/accordions)
		// Example: $textarea.closest('.some-container').on('tab-shown', function() { editor.codemirror.refresh(); });

		// Update textarea value on change in CodeMirror instance
        if (editor && editor.codemirror) {
            editor.codemirror.on('change', function(cm) {
                cm.save(); // Updates the original textarea
                $textarea.trigger('change'); // Trigger change event for other scripts if needed
            });
        }
	});


	// Find the form on the page (assuming only one form with data-consent-type)
	var $form = $('form[data-consent-type]');

	if ($form.length) {
		var consentType = $form.data('consent-type');
		var checkboxName = $form.data('checkbox-name'); // e.g., 'sms_consent' or 'email_consent'
		var optionPrefix = 'cc_' + consentType + '_consent_'; // e.g., 'cc_sms_consent_'

		// JavaScript validation on form submit
		$form.on('submit', function (e) {
			// Make sure CodeMirror instances have saved their content to the textareas
            if (typeof wp !== 'undefined' && typeof wp.codeEditor !== 'undefined') {
                $('.codemirror-textarea').each(function() {
                    var cm = $(this).data('codemirror'); // Get CodeMirror instance if stored
                    if (cm) {
                        cm.save();
                    }
                });
            }

			var checkoutHtml = $('#' + optionPrefix + 'checkout_html').val();
			var registrationHtml = $('#' + optionPrefix + 'registration_html').val();
			var accountHtml = $('#' + optionPrefix + 'account_html').val();

			// Function to validate HTML structure (basic check)
			function isValidHTML(...htmlArgs) {
				return htmlArgs.every(html => {
					try {
						// More robust check than just creating a div, attempt to parse
						var parser = new DOMParser();
						var doc = parser.parseFromString(html, 'text/html');
						// Check if the body contains a parsererror element (indicates failure)
						return !doc.body.querySelector('parsererror');
					} catch (err) {
						// If DOMParser itself throws an error
						return false;
					}
				});
			}

			// Function to check if the required input checkbox exists with correct name and id
			function hasConsentInputBox(...htmlArgs) {
				return htmlArgs.every(html => {
					try {
						var parser = new DOMParser();
						var doc = parser.parseFromString(html, 'text/html');
						// Find input with specific name and id
						const inputTag = doc.querySelector('input[name="' + checkboxName + '"][id="' + checkboxName + '"]');
						// Check if it exists and is a checkbox
						return inputTag && inputTag.type === 'checkbox';
					} catch (err) {
						return false;
					}
				});
			}

			try {
				// Note: Client-side HTML validation is tricky. The PHP validation is the primary one.
				// This basic check helps catch simple syntax errors.
				// if (!isValidHTML(checkoutHtml, registrationHtml, accountHtml)) {
				//  throw new Error('Invalid HTML detected. Please check the syntax in all fields.');
				// }

				if (!hasConsentInputBox(checkoutHtml, registrationHtml, accountHtml)) {
					throw new Error('The "' + checkboxName + '" checkbox (with matching name and id attributes) must be present in all HTML snippets.');
				}
			} catch (error) {
				alert(error.message);
				e.preventDefault(); // Stop form submission
			}
		});
	}
}); 