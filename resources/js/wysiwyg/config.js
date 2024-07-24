import {register as registerShortcuts} from './shortcuts';
import {listen as listenForCommonEvents} from './common-events';
import {scrollToQueryString} from './scrolling';
import {listenForDragAndPaste} from './drop-paste-handling';
import {getPrimaryToolbar, registerAdditionalToolbars} from './toolbars';
import {registerCustomIcons} from './icons';
import {setupFilters} from './filters';

import {getPlugin as getCodeeditorPlugin} from './plugin-codeeditor';
import {getPlugin as getDrawioPlugin} from './plugin-drawio';
import {getPlugin as getCustomhrPlugin} from './plugins-customhr';
import {getPlugin as getImagemanagerPlugin} from './plugins-imagemanager';
import {getPlugin as getAboutPlugin} from './plugins-about';
import {getPlugin as getDetailsPlugin} from './plugins-details';
import {getPlugin as getTableAdditionsPlugin} from './plugins-table-additions';
import {getPlugin as getTasklistPlugin} from './plugins-tasklist';
import {
    handleTableCellRangeEvents,
    handleEmbedAlignmentChanges,
    handleTextDirectionCleaning,
} from './fixes';

const styleFormats = [
    {title: 'Large Header', format: 'h2', preview: 'color: blue;'},
    {title: 'Medium Header', format: 'h3'},
    {title: 'Small Header', format: 'h4'},
    {title: 'Tiny Header', format: 'h5'},
    {
        title: 'Paragraph', format: 'p', exact: true, classes: '',
    },
    {title: 'Blockquote', format: 'blockquote'},
    {
        title: 'Callouts',
        items: [
            {title: 'Information', format: 'calloutinfo'},
            {title: 'Success', format: 'calloutsuccess'},
            {title: 'Warning', format: 'calloutwarning'},
            {title: 'Danger', format: 'calloutdanger'},
        ],
    },
];

const formats = {
    alignleft: {selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img,iframe,video', classes: 'align-left'},
    aligncenter: {selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img,iframe,video', classes: 'align-center'},
    alignright: {selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img,iframe,video', classes: 'align-right'},
    calloutsuccess: {block: 'p', exact: true, attributes: {class: 'callout success'}},
    calloutinfo: {block: 'p', exact: true, attributes: {class: 'callout info'}},
    calloutwarning: {block: 'p', exact: true, attributes: {class: 'callout warning'}},
    calloutdanger: {block: 'p', exact: true, attributes: {class: 'callout danger'}},
};

const colorMap = [
    '#BFEDD2', '',
    '#FBEEB8', '',
    '#F8CAC6', '',
    '#ECCAFA', '',
    '#C2E0F4', '',

    '#2DC26B', '',
    '#F1C40F', '',
    '#E03E2D', '',
    '#B96AD9', '',
    '#3598DB', '',

    '#169179', '',
    '#E67E23', '',
    '#BA372A', '',
    '#843FA1', '',
    '#236FA1', '',

    '#ECF0F1', '',
    '#CED4D9', '',
    '#95A5A6', '',
    '#7E8C8D', '',
    '#34495E', '',

    '#000000', '',
    '#ffffff', '',
];

function filePickerCallback(callback, value, meta) {
    // field_name, url, type, win
    if (meta.filetype === 'file') {
        /** @type {EntitySelectorPopup} * */
        const selector = window.$components.first('entity-selector-popup');
        const selectionText = this.selection.getContent({format: 'text'}).trim();
        selector.show(entity => {
            callback(entity.link, {
                text: entity.name,
                title: entity.name,
            });
        }, {
            initialValue: selectionText,
            searchEndpoint: '/search/entity-selector',
            entityTypes: 'page,book,chapter,bookshelf',
            entityPermission: 'view',
        });
    }

    if (meta.filetype === 'image') {
        // Show image manager
        /** @type {ImageManager} * */
        const imageManager = window.$components.first('image-manager');
        imageManager.show(image => {
            callback(image.url, {alt: image.name});
        }, 'gallery');
    }
}

/**
 * @param {WysiwygConfigOptions} options
 * @return {string[]}
 */
function gatherPlugins(options) {
    const plugins = [
        'image',
        'table',
        'link',
        'autolink',
        'fullscreen',
        'code',
        'customhr',
        'autosave',
        'lists',
        'codeeditor',
        'media',
        'imagemanager',
        'about',
        'details',
        'tasklist',
        'tableadditions',
        options.textDirection === 'rtl' ? 'directionality' : '',
    ];

    window.tinymce.PluginManager.add('codeeditor', getCodeeditorPlugin());
    window.tinymce.PluginManager.add('customhr', getCustomhrPlugin());
    window.tinymce.PluginManager.add('imagemanager', getImagemanagerPlugin());
    window.tinymce.PluginManager.add('about', getAboutPlugin());
    window.tinymce.PluginManager.add('details', getDetailsPlugin());
    window.tinymce.PluginManager.add('tasklist', getTasklistPlugin());
    window.tinymce.PluginManager.add('tableadditions', getTableAdditionsPlugin());

    if (options.drawioUrl) {
        window.tinymce.PluginManager.add('drawio', getDrawioPlugin(options));
        plugins.push('drawio');
    }

    return plugins.filter(plugin => Boolean(plugin));
}

/**
 * Fetch custom HTML head content nodes from the outer page head
 * and add them to the given editor document.
 * @param {Document} editorDoc
 */
function addCustomHeadContent(editorDoc) {
    const headContentLines = document.head.innerHTML.split('\n');
    const startLineIndex = headContentLines.findIndex(line => line.trim() === '<!-- Start: custom user content -->');
    const endLineIndex = headContentLines.findIndex(line => line.trim() === '<!-- End: custom user content -->');
    if (startLineIndex === -1 || endLineIndex === -1) {
        return;
    }

    const customHeadHtml = headContentLines.slice(startLineIndex + 1, endLineIndex).join('\n');
    const el = editorDoc.createElement('div');
    el.innerHTML = customHeadHtml;

    editorDoc.head.append(...el.children);
}

/**
 * @param {WysiwygConfigOptions} options
 * @return {function(Editor)}
 */
function getSetupCallback(options) {
    return function setupCallback(editor) {
        function editorChange() {
            if (options.darkMode) {
                editor.contentDocument.documentElement.classList.add('dark-mode');
            }
            window.$events.emit('editor-html-change', '');
        }

        editor.on('ExecCommand change input NodeChange ObjectResized', editorChange);
        listenForCommonEvents(editor);
        listenForDragAndPaste(editor, options);

        editor.on('init', () => {
            editorChange();
            scrollToQueryString(editor);
            window.editor = editor;
            registerShortcuts(editor);
        });

        editor.on('PreInit', () => {
            setupFilters(editor);
        });

        handleEmbedAlignmentChanges(editor);
        handleTableCellRangeEvents(editor);
        handleTextDirectionCleaning(editor);

        // Custom handler hook
        window.$events.emitPublic(options.containerElement, 'editor-tinymce::setup', {editor});

        // Inline code format button
        editor.ui.registry.addButton('inlinecode', {
            tooltip: 'Inline code',
            icon: 'sourcecode',
            onAction() {
                editor.execCommand('mceToggleFormat', false, 'code');
            },
        });
    };
}

/**
 * @param {WysiwygConfigOptions} options
 */
function getContentStyle(options) {
    return `
html, body, html.dark-mode {
    background: ${options.darkMode ? '#222' : '#fff'};
} 
body {
    padding-left: 15px !important;
    padding-right: 15px !important; 
    height: initial !important;
    margin:0!important; 
    margin-left: auto! important;
    margin-right: auto !important;
    overflow-y: hidden !important;
}`.trim().replace('\n', '');
}

/**
 * @param {WysiwygConfigOptions} options
 * @return {Object}
 */
export function buildForEditor(options) {
    // Set language
    window.tinymce.addI18n(options.language, options.translationMap);

    // BookStack Version
    const version = document.querySelector('script[src*="/dist/app.js"]').getAttribute('src').split('?version=')[1];

    // Return config object
    return {
        width: '100%',
        height: '100%',
        selector: '#html-editor',
        cache_suffix: `?version=${version}`,
        content_css: [
            window.baseUrl('/dist/styles.css'),
        ],
        branding: false,
        skin: options.darkMode ? 'tinymce-5-dark' : 'tinymce-5',
        body_class: 'page-content',
        browser_spellcheck: true,
        relative_urls: false,
        language: options.language,
        directionality: options.textDirection,
        remove_script_host: false,
        document_base_url: window.baseUrl('/'),
        end_container_on_empty_block: true,
        remove_trailing_brs: false,
        statusbar: false,
        menubar: false,
        paste_data_images: false,
        extended_valid_elements: 'pre[*],svg[*],div[drawio-diagram],details[*],summary[*],div[*],li[class|checked|style]',
        automatic_uploads: false,
        custom_elements: 'doc-root,code-block',
        valid_children: [
            '-div[p|h1|h2|h3|h4|h5|h6|blockquote|code-block]',
            '+div[pre|img]',
            '-doc-root[doc-root|#text]',
            '-li[details]',
            '+code-block[pre]',
            '+doc-root[p|h1|h2|h3|h4|h5|h6|blockquote|code-block|div|hr]',
        ].join(','),
        plugins: gatherPlugins(options),
        contextmenu: false,
        toolbar: getPrimaryToolbar(options),
        content_style: getContentStyle(options),
        style_formats: styleFormats,
        style_formats_merge: false,
        media_alt_source: false,
        media_poster: false,
        formats,
        table_style_by_css: true,
        table_use_colgroups: true,
        file_picker_types: 'file image',
        color_map: colorMap,
        file_picker_callback: filePickerCallback,
        paste_preprocess(plugin, args) {
            const {content} = args;
            if (content.indexOf('<img src="file://') !== -1) {
                args.content = '';
            }
        },
        init_instance_callback(editor) {
            addCustomHeadContent(editor.getDoc());
        },
        setup(editor) {
            registerCustomIcons(editor);
            registerAdditionalToolbars(editor);
            getSetupCallback(options)(editor);
        },
    };
}

/**
 * @param {WysiwygConfigOptions} options
 * @return {RawEditorOptions}
 */
export function buildForInput(options) {
    // Set language
    window.tinymce.addI18n(options.language, options.translationMap);

    // BookStack Version
    const version = document.querySelector('script[src*="/dist/app.js"]').getAttribute('src').split('?version=')[1];

    // Return config object
    return {
        width: '100%',
        height: '185px',
        target: options.containerElement,
        cache_suffix: `?version=${version}`,
        content_css: [
            window.baseUrl('/dist/styles.css'),
        ],
        branding: false,
        skin: options.darkMode ? 'tinymce-5-dark' : 'tinymce-5',
        body_class: 'wysiwyg-input',
        browser_spellcheck: true,
        relative_urls: false,
        language: options.language,
        directionality: options.textDirection,
        remove_script_host: false,
        document_base_url: window.baseUrl('/'),
        end_container_on_empty_block: true,
        remove_trailing_brs: false,
        statusbar: false,
        menubar: false,
        plugins: 'link autolink lists',
        contextmenu: false,
        toolbar: 'bold italic link bullist numlist',
        content_style: getContentStyle(options),
        file_picker_types: 'file',
        valid_elements: 'p,a[href|title|target],ol,ul,li,strong,em,br',
        file_picker_callback: filePickerCallback,
        init_instance_callback(editor) {
            addCustomHeadContent(editor.getDoc());

            editor.contentDocument.documentElement.classList.toggle('dark-mode', options.darkMode);
        },
    };
}

/**
 * @typedef {Object} WysiwygConfigOptions
 * @property {Element} containerElement
 * @property {string} language
 * @property {boolean} darkMode
 * @property {string} textDirection
 * @property {string} drawioUrl
 * @property {int} pageId
 * @property {Object} translations
 * @property {Object} translationMap
 */
