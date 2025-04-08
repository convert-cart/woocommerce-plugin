/* global convertCartAdminData, wp, CodeMirror, html_beautify */

jQuery(document).ready(function($) {

    // Check if the editor elements exist
    if (jQuery('.consent-html-editor').length === 0) {
        return;
    }

    // Check for localized data
    if (typeof convertCartAdminData === 'undefined') {
         return;
    }

     // Check for wp.codeEditor
    if (typeof wp === 'undefined' || typeof wp.codeEditor === 'undefined') {
         return;
    }

     // Check for html_beautify (optional)
    if (typeof html_beautify !== 'function') {
    }

    var beautifyOptions = {
        indent_size: 2,
        space_in_empty_paren: true,
        wrap_line_length: 0 // Disable automatic line wrapping
    };

    $('.consent-html-editor').each(function() {
        var $textarea = $(this);
        var editorId = $textarea.attr('id');

        var editorSettings = wp.codeEditor.defaultSettings ?
            _.clone(wp.codeEditor.defaultSettings) : {};

        // Merge specific CodeMirror settings
        // Ensure OUR settings are merged last to take precedence
        editorSettings.codemirror = _.extend(
            {}, // Start with empty object
            editorSettings.codemirror, // Base defaults
            convertCartAdminData.editorSettings, // Settings from WP/localize
            { // Our required settings
                indentWithTabs: false,
                tabSize: 2,
                indentUnit: 2 // Explicitly set indentUnit too
            }
        );

        // Console Log for Debugging
        console.log('CodeMirror Settings for ' + editorId + ':', editorSettings.codemirror);

        // Initialize CodeMirror
        var cmInstance;
        try {
            // Initialize with potentially incorrect settings first
            cmInstance = wp.codeEditor.initialize($textarea, editorSettings).codemirror;

            // --- FIX: Set options directly on the instance AFTER init ---
            cmInstance.setOption('indentUnit', 2);
            cmInstance.setOption('tabSize', 2);
            cmInstance.setOption('indentWithTabs', false);
            // --- END FIX ---

        } catch (e) {
            $textarea.show(); // Fallback to plain textarea if init fails
            console.error('CodeMirror initialization failed for:', $textarea[0], e);
            return; // Stop processing this textarea if init fails
        }

        // --- Add Format & Reset Buttons ---
        var $wrapper = $textarea.closest('.CodeMirror');
        var $buttonContainer = $('<div class="cc-editor-buttons"></div>').insertAfter($wrapper);

        // Format Button
        var $formatBtn = $('<button type="button" class="button button-secondary cc-format-btn"></button>')
            .text(convertCartAdminData.i18n.formatButton || 'Format HTML'); // Localized text

        $formatBtn.on('click', function() {
            if (typeof html_beautify === 'function') {
                try {
                    var currentCode = cmInstance.getValue();
                    var formattedCode = html_beautify(currentCode, beautifyOptions);
                    cmInstance.setValue(formattedCode);
                } catch(e) {
                     // alert('Code formatting failed.');
                }
            } else {
                alert('HTML Beautify library not loaded.');
            }
        });

        // Reset Button
        var $resetBtn = $('<button type="button" class="button button-secondary cc-reset-btn"></button>')
            .text(convertCartAdminData.i18n.resetButton || 'Reset to Default'); // Localized text
        var consentType = $textarea.data('consent-type'); // Get type for default template

        $resetBtn.on('click', function() {
            var defaultHtml = convertCartAdminData.defaultTemplates[consentType];
            if (typeof defaultHtml !== 'undefined') {
                if (confirm(convertCartAdminData.i18n.resetConfirm || 'Are you sure you want to reset this template to its default?')) {
                    cmInstance.setValue(defaultHtml);
                    // Re-format after resetting
                    if (typeof html_beautify === 'function') {
                         try {
                            var formattedDefault = html_beautify(defaultHtml, beautifyOptions);
                            cmInstance.setValue(formattedDefault);
                         } catch(e) { }
                    }
                }
            } else {
                 alert('Default template data not found.');
            }
        });

        $buttonContainer.append($formatBtn, $resetBtn);
        // --- ADD Console Log ---
        console.log('Buttons appended for editor related to textarea:', $textarea[0]);
        // --- END Console Log ---

        // --- Initial Formatting & Refresh ---
        if (typeof html_beautify === 'function') {
            try {
                var initialCode = cmInstance.getValue();
                if (initialCode && initialCode.trim() !== '') {
                    var formattedInitialCode = html_beautify(initialCode, beautifyOptions);
                    // Set value only if formatting changed the code
                    if (initialCode !== formattedInitialCode) {
                         cmInstance.setValue(formattedInitialCode);
                    }
                }
            } catch(e) { }
        }

        // Refresh CodeMirror to ensure proper rendering
        setTimeout(function() {
            try {
                cmInstance.refresh();
            } catch(e) { }
        }, 100);

    }); // End .each

}); // End document ready