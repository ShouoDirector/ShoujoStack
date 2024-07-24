/**
 * @param {WysiwygConfigOptions} options
 * @return {String}
 */
export function getPrimaryToolbar(options) {
    const textDirPlugins = options.textDirection === 'rtl' ? 'ltr rtl' : '';

    const toolbar = [
        'undo redo',
        'styles',
        'bold italic underline forecolor backcolor formatoverflow',
        'alignleft aligncenter alignright alignjustify',
        'bullist numlist listoverflow',
        textDirPlugins,
        'link customtable imagemanager-insert insertoverflow',
        'code about fullscreen',
    ];

    return toolbar.filter(row => Boolean(row)).join(' | ');
}

/**
 * @param {Editor} editor
 */
function registerPrimaryToolbarGroups(editor) {
    editor.ui.registry.addGroupToolbarButton('formatoverflow', {
        icon: 'more-drawer',
        tooltip: 'More',
        items: 'strikethrough superscript subscript inlinecode removeformat',
    });
    editor.ui.registry.addGroupToolbarButton('listoverflow', {
        icon: 'more-drawer',
        tooltip: 'More',
        items: 'tasklist outdent indent',
    });
    editor.ui.registry.addGroupToolbarButton('insertoverflow', {
        icon: 'more-drawer',
        tooltip: 'More',
        items: 'customhr codeeditor drawio media details',
    });
}

/**
 * @param {Editor} editor
 */
function registerLinkContextToolbar(editor) {
    editor.ui.registry.addContextToolbar('linkcontexttoolbar', {
        predicate(node) {
            return node.closest('a') !== null;
        },
        position: 'node',
        scope: 'node',
        items: 'link unlink openlink',
    });
}

/**
 * @param {Editor} editor
 */
function registerImageContextToolbar(editor) {
    editor.ui.registry.addContextToolbar('imagecontexttoolbar', {
        predicate(node) {
            return node.closest('img') !== null && !node.hasAttribute('data-mce-object');
        },
        position: 'node',
        scope: 'node',
        items: 'image',
    });
}

/**
 * @param {Editor} editor
 */
function registerObjectContextToolbar(editor) {
    editor.ui.registry.addContextToolbar('objectcontexttoolbar', {
        predicate(node) {
            return node.closest('img') !== null && node.hasAttribute('data-mce-object');
        },
        position: 'node',
        scope: 'node',
        items: 'media',
    });
}

/**
 * @param {Editor} editor
 */
export function registerAdditionalToolbars(editor) {
    registerPrimaryToolbarGroups(editor);
    registerLinkContextToolbar(editor);
    registerImageContextToolbar(editor);
    registerObjectContextToolbar(editor);
}
