/* global convertCartAdminData, wp, CodeMirror, html_beautify */

jQuery(document).ready(function($) {

    // Check if the editor elements exist
    if (jQuery('.consent-html-editor').length === 0) {
        return;
    }

    // Check for required dependencies
    if (typeof convertCartAdminData === 'undefined' || 
        typeof wp === 'undefined' || 
        typeof wp.codeEditor === 'undefined') {
        return;
    }

     // Check for html_beautify (optional)
    if (typeof html_beautify !== 'function') {
    }

    var beautifyOptions = {
        indent_size: 2,
        indent_char: ' ',
        max_preserve_newlines: 2,
        preserve_newlines: true,
        wrap_line_length: 0
    };

    $('.consent-html-editor').each(function() {
        var $textarea = $(this);
        var consentType = $textarea.data('consent-type'); // Get type before initializing editor
        
        // Get base editor settings
        var editorSettings = wp.codeEditor.defaultSettings ? 
            _.clone(wp.codeEditor.defaultSettings) : {};

        // Override CodeMirror settings
        editorSettings.codemirror = _.extend(
            {},
            editorSettings.codemirror,
            {
                lineNumbers: true,
                lineWrapping: true,
                indentUnit: 2,
                tabSize: 2,
                indentWithTabs: false,
                mode: 'text/html'
            }
        );

        // Initialize CodeMirror
        var cmInstance;
        try {
            cmInstance = wp.codeEditor.initialize($textarea, editorSettings).codemirror;
            
            // Force refresh to ensure proper rendering
            setTimeout(function() {
                cmInstance.refresh();
            }, 100);

            // Create button container after CodeMirror is initialized
            var $editorWrapper = $(cmInstance.getWrapperElement());
            var $buttonContainer = $('<div/>', {
                'class': 'cc-editor-buttons'
            }).insertAfter($editorWrapper);

            // Add Format button
            $('<button/>', {
                type: 'button',
                'class': 'button button-secondary cc-format-btn',
                text: convertCartAdminData.i18n.formatButton || 'Format HTML'
            }).appendTo($buttonContainer).on('click', function() {
                if (typeof html_beautify === 'function') {
                    try {
                        var currentCode = cmInstance.getValue();
                        var formattedCode = html_beautify(currentCode, beautifyOptions);
                        cmInstance.setValue(formattedCode);
                    } catch(e) {
                        console.error('Format failed:', e);
                    }
                }
            });

            // Add Reset button
            $('<button/>', {
                type: 'button',
                'class': 'button button-secondary cc-reset-btn',
                text: convertCartAdminData.i18n.resetButton || 'Reset to Default'
            }).appendTo($buttonContainer).on('click', function() {
                var defaultHtml = convertCartAdminData.defaultTemplates[consentType];
                if (defaultHtml && confirm(convertCartAdminData.i18n.resetConfirm)) {
                    cmInstance.setValue(defaultHtml);
                    if (typeof html_beautify === 'function') {
                        try {
                            var formattedDefault = html_beautify(defaultHtml, beautifyOptions);
                            cmInstance.setValue(formattedDefault);
                        } catch(e) {
                            console.error('Format failed:', e);
                        }
                    }
                }
            });

            // Format initial content
            if (typeof html_beautify === 'function') {
                try {
                    var initialCode = cmInstance.getValue();
                    if (initialCode && initialCode.trim()) {
                        var formattedInitial = html_beautify(initialCode, beautifyOptions);
                        if (initialCode !== formattedInitial) {
                            cmInstance.setValue(formattedInitial);
                        }
                    }
                } catch(e) {
                    console.error('Initial format failed:', e);
                }
            }

        } catch (e) {
            console.error('CodeMirror initialization failed:', e);
            $textarea.show();
            return;
        }
    });

}); // End document ready
