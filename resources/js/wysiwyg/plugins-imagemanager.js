/**
 * @param {Editor} editor
 */
function register(editor) {
    // Custom Image picker button
    editor.ui.registry.addButton('imagemanager-insert', {
        title: 'Insert image',
        icon: 'image',
        tooltip: 'Insert image',
        onAction() {
            /** @type {ImageManager} * */
            const imageManager = window.$components.first('image-manager');
            imageManager.show(image => {
                const imageUrl = image.thumbs?.display || image.url;
                let html = `<a href="${image.url}" target="_blank">`;
                html += `<img src="${imageUrl}" alt="${image.name}">`;
                html += '</a>';
                editor.execCommand('mceInsertContent', false, html);
            }, 'gallery');
        },
    });
}

/**
 * @return {register}
 */
export function getPlugin() {
    return register;
}
