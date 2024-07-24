/**
 * @param {Editor} editor
 */
export function register(editor) {
    // Headers
    for (let i = 1; i < 5; i++) {
        editor.shortcuts.add(`meta+${i}`, '', ['FormatBlock', false, `h${i + 1}`]);
    }

    // Other block shortcuts
    editor.shortcuts.add('meta+5', '', ['FormatBlock', false, 'p']);
    editor.shortcuts.add('meta+d', '', ['FormatBlock', false, 'p']);
    editor.shortcuts.add('meta+6', '', ['FormatBlock', false, 'blockquote']);
    editor.shortcuts.add('meta+q', '', ['FormatBlock', false, 'blockquote']);
    editor.shortcuts.add('meta+7', '', ['codeeditor', false, 'pre']);
    editor.shortcuts.add('meta+e', '', ['codeeditor', false, 'pre']);
    editor.shortcuts.add('meta+8', '', ['FormatBlock', false, 'code']);
    editor.shortcuts.add('meta+shift+E', '', ['FormatBlock', false, 'code']);
    editor.shortcuts.add('meta+o', '', 'InsertOrderedList');
    editor.shortcuts.add('meta+p', '', 'InsertUnorderedList');

    // Save draft shortcut
    editor.shortcuts.add('meta+S', '', () => {
        window.$events.emit('editor-save-draft');
    });

    // Save page shortcut
    editor.shortcuts.add('meta+13', '', () => {
        window.$events.emit('editor-save-page');
    });

    // Loop through callout styles
    editor.shortcuts.add('meta+9', '', () => {
        const selectedNode = editor.selection.getNode();
        const callout = selectedNode ? selectedNode.closest('.callout') : null;

        const formats = ['info', 'success', 'warning', 'danger'];
        const currentFormatIndex = formats.findIndex(format => {
            return callout && callout.classList.contains(format);
        });
        const newFormatIndex = (currentFormatIndex + 1) % formats.length;
        const newFormat = formats[newFormatIndex];

        editor.formatter.apply(`callout${newFormat}`);
    });

    // Link selector shortcut
    editor.shortcuts.add('meta+shift+K', '', () => {
        /** @var {EntitySelectorPopup} * */
        const selectorPopup = window.$components.first('entity-selector-popup');
        const selectionText = editor.selection.getContent({format: 'text'}).trim();
        selectorPopup.show(entity => {
            if (editor.selection.isCollapsed()) {
                editor.insertContent(editor.dom.createHTML('a', {href: entity.link}, editor.dom.encode(entity.name)));
            } else {
                editor.formatter.apply('link', {href: entity.link});
            }

            editor.selection.collapse(false);
            editor.focus();
        }, {
            initialValue: selectionText,
            searchEndpoint: '/search/entity-selector',
            entityTypes: 'page,book,chapter,bookshelf',
            entityPermission: 'view',
        });
    });
}
