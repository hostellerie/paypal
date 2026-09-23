/* PayPal product description advanced editor.
 *
 * Loaded through Geeklog's COM_setupAdvancedEditor() API.
 */
(function () {
    'use strict';

    function initPayPalProductEditor() {
        var editorName = typeof geeklogEditorName !== 'undefined'
            ? geeklogEditorName
            : 'ckeditor';

        if (typeof AdvancedEditor !== 'undefined'
            && AdvancedEditor.api
            && AdvancedEditor.api[editorName]
            && typeof AdvancedEditor.api[editorName].newEditor === 'function'
            && document.getElementById('description')
        ) {
            AdvancedEditor.api[editorName].newEditor('description', {
                toolbar: 1
            });
            return;
        }

        if (typeof CKEDITOR !== 'undefined'
            && document.getElementById('description')
            && !CKEDITOR.instances.description
        ) {
            CKEDITOR.replace('description');
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPayPalProductEditor);
    } else {
        initPayPalProductEditor();
    }
}());
