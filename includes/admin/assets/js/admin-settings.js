/* global jQuery, _, wp, ccAdminSettings */
jQuery(document).ready(function ($) {
	if (typeof wp === 'undefined' || typeof wp.codeEditor === 'undefined' || typeof ccAdminSettings === 'undefined' || typeof ccAdminSettings.codeEditorSettings === 'undefined') {
		console.error('CodeMirror settings not available for CC Analytics.');
		return;
	}

	$('.codemirror-textarea').each(function () {
		var $textarea = $(this);
		var editorSettings = ccAdminSettings.codeEditorSettings;

		editorSettings.codemirror = _.extend(
			{},
			editorSettings.codemirror,
			{
				mode: 'htmlmixed',
				indentUnit: 2,
				tabSize: 2,
				lineNumbers: true,
				theme: 'default'
			}
		);

		var editor = wp.codeEditor.initialize($textarea, editorSettings);

		if (editor && editor.codemirror) {
			editor.codemirror.on('change', function(cm) {
				cm.save();
				$textarea.trigger('change');
			});
		}
	});

	var $form = $('form[data-consent-type]');

	if ($form.length) {
		var consentType = $form.data('consent-type');
		var checkboxName = $form.data('checkbox-name');
		var optionPrefix = 'cc_' + consentType + '_consent_';

		$form.on('submit', function (e) {
			if (typeof wp !== 'undefined' && typeof wp.codeEditor !== 'undefined') {
				$('.codemirror-textarea').each(function() {
					var editorId = $(this).attr('id');
					if (editorId && wp.codeEditor && wp.codeEditor.defaultSettings) {
						var editor = wp.codeEditor.getSettings(document.getElementById(editorId));
						if (editor && editor.codemirror) {
							editor.codemirror.save();
						}
					}
				});
			}

			var checkoutHtml = $('#' + optionPrefix + 'checkout_html').val();
			var registrationHtml = $('#' + optionPrefix + 'registration_html').val();
			var accountHtml = $('#' + optionPrefix + 'account_html').val();

			function hasConsentInputBox(...htmlArgs) {
				return htmlArgs.every(html => {
					try {
						var parser = new DOMParser();
						var doc = parser.parseFromString(html, 'text/html');
						const inputTag = doc.querySelector('input[name="' + checkboxName + '"][id="' + checkboxName + '"]');
						return inputTag && inputTag.type === 'checkbox';
					} catch (err) {
						return false;
					}
				});
			}

			try {
				if (!hasConsentInputBox(checkoutHtml, registrationHtml, accountHtml)) {
					throw new Error('The "' + checkboxName + '" checkbox (with matching name and id attributes) must be present in all HTML snippets.');
				}

				$('.cc-settings-error').remove();
				
			} catch (error) {
				e.preventDefault();
				$('<div class="notice notice-error cc-settings-error"><p>' + error.message + '</p></div>')
					.insertBefore($form);
				
				$('html, body').animate({
					scrollTop: $('.cc-settings-error').offset().top - 50
				}, 500);
			}
		});
	}
}); 