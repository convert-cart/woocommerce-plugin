/* global convertCartAdminData, wp, CodeMirror, html_beautify */
// Simple test script
console.log('ConvertCart Admin JS: File Loaded (Top Level)');

jQuery(document).ready(function($) {
    console.log('ConvertCart Admin JS: Document Ready');

    // Check if the textarea element exists
    if (jQuery('.consent-html-editor').length > 0) {
        console.log('ConvertCart Admin JS: Found .consent-html-editor elements.');
    } else {
        console.warn('ConvertCart Admin JS: Did not find any .consent-html-editor elements.');
    }

    // Basic check for localized data
    if (typeof convertCartAdminData !== 'undefined') {
         console.log('ConvertCart Admin JS: convertCartAdminData IS defined.');
    } else {
         console.error('ConvertCart Admin JS: convertCartAdminData IS NOT defined.');
    }

     // Basic check for wp.codeEditor
    if (typeof wp !== 'undefined' && typeof wp.codeEditor !== 'undefined') {
         console.log('ConvertCart Admin JS: wp.codeEditor IS defined.');
    } else {
         console.error('ConvertCart Admin JS: wp.codeEditor IS NOT defined.');
    }

     // Basic check for html_beautify
    if (typeof html_beautify === 'function') {
         console.log('ConvertCart Admin JS: html_beautify IS defined.');
    } else {
         console.warn('ConvertCart Admin JS: html_beautify IS NOT defined (may load later).');
    }

    // --- Initial Checks ---
    if (typeof wp === 'undefined' || typeof wp.codeEditor === 'undefined') {
        console.error('ConvertCart Admin: wp.codeEditor is not available.');
        return;
    }
    if (typeof convertCartAdminData === 'undefined') {
        console.error('ConvertCart Admin: Localized data (convertCartAdminData) is missing.');
        return;
    }
    if (typeof CodeMirror === 'undefined') {
        // This might happen if wp.codeEditor fails internally
        console.error('ConvertCart Admin: CodeMirror global object is not available.');
    }
    // Check specifically for the beautifier function *after* document ready
    if (typeof html_beautify !== 'function') {
        console.warn('ConvertCart Admin: html_beautify function not found after document ready. Formatting will likely fail.');
    } else {
        console.log('ConvertCart Admin: html_beautify function IS available after document ready.');
    }

    console.log('ConvertCart Admin: Script loaded. Using editor settings:', convertCartAdminData.editorSettings);

    // --- Beautify Options ---
    var beautifyOptions = {
        // indent_size: 4, // Size is irrelevant for tabs
        indent_char: '\t', // Use tab character for indentation
        max_preserve_newlines: 1,
        preserve_newlines: true,
        keep_array_indentation: false,
        break_chained_methods: false,
        indent_scripts: 'normal',
        brace_style: 'collapse',
        space_before_conditional: true,
        unescape_strings: false,
        jslint_happy: false,
        end_with_newline: false,
        wrap_line_length: 0,
        indent_inner_html: true,
        comma_first: false,
        e4x: false,
        indent_empty_lines: false
    };

    // --- Process Each Editor ---
    $('.consent-html-editor').each(function(index) {
        var $textarea = $(this);
        var editorId = $textarea.attr('id') || $textarea.attr('name') || 'editor-' + index;
        var consentType = $textarea.data('consent-type');

        console.log(`ConvertCart Admin: Processing editor [${editorId}] for type [${consentType}]`);

        // --- Setup Containers ---
        var $container = $('<div>', { class: 'editor-container' });
        var $buttonContainer = $('<div>', { class: 'button-container' });
        $textarea.wrap($container);
        $textarea.after($buttonContainer);

        // --- Initialize CodeMirror ---
        // Use the settings passed directly from wp_enqueue_code_editor
        var editorSettings = convertCartAdminData.editorSettings || {};
        var editorInstance = wp.codeEditor.initialize($textarea, editorSettings);

        if (!editorInstance || typeof editorInstance.codemirror === 'undefined') {
             console.error(`ConvertCart Admin: Failed to initialize CodeMirror for [${editorId}]`);
             // Attempt to remove the containers if init failed
             $buttonContainer.remove();
             if ($textarea.parent().is('.editor-container')) {
                 $textarea.unwrap();
             }
             return; // Skip this textarea
        }
        console.log(`ConvertCart Admin: CodeMirror initialized successfully for [${editorId}]`);
        var cmInstance = editorInstance.codemirror;

        // --- Create Buttons ---
        // Format Button
        var $formatBtn = $('<button>', {
            type: 'button',
            class: 'button button-secondary format-button',
            text: convertCartAdminData.i18n.formatButton || 'Format HTML'
        }).on('click', function() {
            console.log(`ConvertCart Admin: Format button clicked for [${editorId}]`);
            if (typeof html_beautify === 'function') {
                console.log(`ConvertCart Admin: html_beautify IS available inside click handler for [${editorId}].`);
                try {
                    var currentCode = cmInstance.getValue();
                    var formattedCode = html_beautify(currentCode, beautifyOptions);
                    if (currentCode !== formattedCode) {
                        cmInstance.setValue(formattedCode);
                        console.log(`ConvertCart Admin: Formatting applied for [${editorId}]`);
                    } else {
                        console.log(`ConvertCart Admin: Code already formatted for [${editorId}]`);
                    }
                } catch (e) {
                    console.error(`ConvertCart Admin: Error during HTML formatting for [${editorId}]:`, e);
                    alert('An error occurred while formatting the HTML. Check console for details.');
                }
            } else {
                console.error(`ConvertCart Admin: html_beautify IS NOT available inside click handler for [${editorId}].`);
                alert('HTML formatting library (js-beautify) not loaded or available when needed.');
            }
        });

        // Reset Button
        var $resetBtn = $('<button>', {
            type: 'button',
            class: 'button button-secondary reset-button',
            text: convertCartAdminData.i18n.resetButton || 'Reset to Default'
        }).on('click', function() {
            console.log(`ConvertCart Admin: Reset button clicked for [${editorId}]`);
            var defaultHtml = convertCartAdminData.defaultTemplates[consentType];
            if (typeof defaultHtml !== 'undefined') {
                if (confirm(convertCartAdminData.i18n.resetConfirm || 'Are you sure you want to reset?')) {
                    cmInstance.setValue(defaultHtml);
                    // Optionally re-format after resetting
                    if (typeof html_beautify === 'function') {
                         try {
                            var formattedDefault = html_beautify(defaultHtml, beautifyOptions);
                            cmInstance.setValue(formattedDefault);
                         } catch(e) {
                             console.error(`ConvertCart Admin: Error formatting default HTML for [${editorId}]:`, e);
                         }
                    }
                    console.log(`ConvertCart Admin: Reset HTML applied for [${editorId}]`);
                }
            } else {
                 console.warn(`ConvertCart Admin: Default template not found for type [${consentType}] for editor [${editorId}]`);
            }
        });

        // Append buttons
        $buttonContainer.append($formatBtn, $resetBtn);
        console.log(`ConvertCart Admin: Buttons added for [${editorId}]`);

        // --- Initial Formatting & Refresh ---
        if (typeof html_beautify === 'function') {
            try {
                var initialCode = cmInstance.getValue();
                if (initialCode && initialCode.trim() !== '') {
                    var formattedInitialCode = html_beautify(initialCode, beautifyOptions);
                    // Only set if different to avoid unnecessary changes
                    if (initialCode !== formattedInitialCode) {
                         cmInstance.setValue(formattedInitialCode);
                         console.log(`ConvertCart Admin: Initial formatting applied for [${editorId}]`);
                    }
                }
            } catch(e) {
                console.error(`ConvertCart Admin: Error formatting initial HTML for [${editorId}]:`, e);
            }
        } else {
            console.warn(`ConvertCart Admin: html_beautify not available for initial formatting on [${editorId}].`);
        }

        // Refresh the editor - crucial for CodeMirror to render correctly
        setTimeout(function() {
            cmInstance.refresh();
            console.log(`ConvertCart Admin: Refreshed editor [${editorId}]`);
        }, 200); // Slightly longer delay just in case

    }); // End .each loop

    console.log('ConvertCart Admin: Initialization loop finished.');

}); // End document ready 

console.log('ConvertCart Admin JS: File Execution Finished (Bottom Level)'); 