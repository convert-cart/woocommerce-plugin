/* global convertCartAdminData, wp, CodeMirror, html_beautify */

jQuery(document).ready(function($) {
    if (jQuery('.consent-html-editor').length === 0) {
        return;
    }

    if (typeof convertCartAdminData === 'undefined' || 
        typeof wp === 'undefined' || 
        typeof wp.codeEditor === 'undefined') {
        return;
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
        var consentType = $textarea.data('consent-type');
        
        var editorSettings = wp.codeEditor.defaultSettings ? 
            _.clone(wp.codeEditor.defaultSettings) : {};

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

        var cmInstance;
        try {
            cmInstance = wp.codeEditor.initialize($textarea, editorSettings).codemirror;
            
            setTimeout(function() {
                cmInstance.refresh();
            }, 100);

            var $editorWrapper = $(cmInstance.getWrapperElement());
            var $buttonContainer = $('<div/>', {
                'class': 'cc-editor-buttons'
            }).insertAfter($editorWrapper);

            $('<button/>', {
                type: 'button',
                'class': 'button button-secondary cc-format-btn',
                text: convertCartAdminData.i18n.formatButton || 'Format HTML'
            }).appendTo($buttonContainer).on('click', function() {
                if (typeof html_beautify === 'function') {
                    var currentCode = cmInstance.getValue();
                    var formattedCode = html_beautify(currentCode, beautifyOptions);
                    cmInstance.setValue(formattedCode);
                }
            });

            $('<button/>', {
                type: 'button',
                'class': 'button button-secondary cc-reset-btn',
                text: convertCartAdminData.i18n.resetButton || 'Reset to Default'
            }).appendTo($buttonContainer).on('click', function() {
                var defaultHtml = convertCartAdminData.defaultTemplates[consentType];
                if (defaultHtml && confirm(convertCartAdminData.i18n.resetConfirm)) {
                    cmInstance.setValue(defaultHtml);
                    if (typeof html_beautify === 'function') {
                        var formattedDefault = html_beautify(defaultHtml, beautifyOptions);
                        cmInstance.setValue(formattedDefault);
                    }
                }
            });

            if (typeof html_beautify === 'function') {
                var initialCode = cmInstance.getValue();
                if (initialCode && initialCode.trim()) {
                    var formattedInitial = html_beautify(initialCode, beautifyOptions);
                    if (initialCode !== formattedInitial) {
                        cmInstance.setValue(formattedInitial);
                    }
                }
            }
        } catch (e) {
            $textarea.show();
        }
    });
});
