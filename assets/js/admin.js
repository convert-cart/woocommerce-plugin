/* global convertCartAdminData, wp, CodeMirror, html_beautify */

jQuery(document).ready(function($) {

    // Check if the textarea element exists
    if (jQuery('.consent-html-editor').length === 0) {
        return; // Stop if editors aren't present
    }

    // Basic check for localized data
    if (typeof convertCartAdminData === 'undefined') {
         return;
    }

     // Basic check for wp.codeEditor
    if (typeof wp === 'undefined' || typeof wp.codeEditor === 'undefined') {
         return;
    }

     // Basic check for html_beautify (can be deferred)
    if (typeof html_beautify !== 'function') {
    }

    var beautifyOptions = {
        indent_size: 2,
        space_in_empty_paren: true,
        wrap_line_length: 0 // Don't wrap lines automatically
    };

    $('.consent-html-editor').each(function() {
        var $textarea = $(this);
        var editorId = $textarea.attr('id');

        var editorSettings = wp.codeEditor.defaultSettings ?
            _.clone(wp.codeEditor.defaultSettings) : {};

        // Merge our specific settings
        editorSettings.codemirror = _.extend({},
            editorSettings.codemirror,
            convertCartAdminData.editorSettings // Use localized settings
        );

        // Initialize the CodeMirror editor
        var cmInstance;
        try {
            cmInstance = wp.codeEditor.initialize($textarea, editorSettings).codemirror;
        } catch (e) {
            $textarea.show();
            return; // Skip this editor if initialization fails
        }

        // --- Add Buttons ---
        var $wrapper = $textarea.closest('.codemirror-wrapper-div'); // Find the wrapper we added
        if ($wrapper.length === 0) {
             return; // Skip adding buttons if wrapper not found
        }

        var $buttonContainer = $('<div class="convertcart-editor-buttons" style="margin-top: 5px;"></div>');
        $wrapper.after($buttonContainer); // Place buttons after the editor wrapper

        // Format Button
        var $formatBtn = $('<button type="button" class="button button-secondary format-html-btn" style="margin-right: 5px;"></button>')
            .text(convertCartAdminData.i18n.formatButton || 'Format HTML'); // Use localized text

        $formatBtn.on('click', function() {
            if (typeof html_beautify === 'function') {
                try {
                    var currentCode = cmInstance.getValue();
                    var formattedCode = html_beautify(currentCode, beautifyOptions);
                    if (currentCode !== formattedCode) {
                        cmInstance.setValue(formattedCode);
                    }
                } catch (e) {
                    alert('Error formatting HTML. Check browser console.');
                }
            } else {
                alert('HTML formatting library not loaded.');
            }
        });

        // Reset Button
        var $resetBtn = $('<button type="button" class="button button-secondary reset-html-btn"></button>')
            .text(convertCartAdminData.i18n.resetButton || 'Reset to Default'); // Use localized text
        var consentType = $textarea.data('consent-type'); // Get type from data attribute

        $resetBtn.on('click', function() {
            var defaultHtml = convertCartAdminData.defaultTemplates[consentType];
            if (typeof defaultHtml !== 'undefined') {
                if (confirm(convertCartAdminData.i18n.resetConfirm || 'Are you sure you want to reset this template to its default?')) {
                    cmInstance.setValue(defaultHtml);
                    // Optionally re-format after resetting
                    if (typeof html_beautify === 'function') {
                         try {
                            var formattedDefault = html_beautify(defaultHtml, beautifyOptions);
                            cmInstance.setValue(formattedDefault);
                         } catch(e) {
                         }
                    }
                }
            } else {
                 alert('Default template data not found.');
            }
        });

        // Append buttons
        $buttonContainer.append($formatBtn, $resetBtn);

        // --- Initial Formatting & Refresh ---
        if (typeof html_beautify === 'function') {
            try {
                var initialCode = cmInstance.getValue();
                if (initialCode && initialCode.trim() !== '') {
                    var formattedInitialCode = html_beautify(initialCode, beautifyOptions);
                    // Only set if different to avoid unnecessary changes
                    if (initialCode !== formattedInitialCode) {
                         cmInstance.setValue(formattedInitialCode);
                    }
                }
            } catch(e) {
            }
        }

        // Refresh the editor - crucial for CodeMirror to render correctly
        setTimeout(function() {
            try {
                cmInstance.refresh();
            } catch(e) {
            }
        }, 100);

    }); // End .each loop

}); // End document ready