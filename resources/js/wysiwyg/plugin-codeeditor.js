function elemIsCodeBlock(elem) {
    return elem.tagName.toLowerCase() === 'code-block';
}

/**
 * @param {Editor} editor
 * @param {String} code
 * @param {String} language
 * @param {String} direction
 * @param {function(string, string)} callback (Receives (code: string,language: string)
 */
function showPopup(editor, code, language, direction, callback) {
    /** @var {CodeEditor} codeEditor * */
    const codeEditor = window.$components.first('code-editor');
    const bookMark = editor.selection.getBookmark();
    codeEditor.open(code, language, direction, (newCode, newLang) => {
        callback(newCode, newLang);
        editor.focus();
        editor.selection.moveToBookmark(bookMark);
    }, () => {
        editor.focus();
        editor.selection.moveToBookmark(bookMark);
    });
}

/**
 * @param {Editor} editor
 * @param {CodeBlockElement} codeBlock
 */
function showPopupForCodeBlock(editor, codeBlock) {
    const direction = codeBlock.getAttribute('dir') || '';
    showPopup(editor, codeBlock.getContent(), codeBlock.getLanguage(), direction, (newCode, newLang) => {
        codeBlock.setContent(newCode, newLang);
    });
}

/**
 * Define our custom code-block HTML element that we use.
 * Needs to be delayed since it needs to be defined within the context of the
 * child editor window and document, hence its definition within a callback.
 * @param {Editor} editor
 */
function defineCodeBlockCustomElement(editor) {
    const doc = editor.getDoc();
    const win = doc.defaultView;

    class CodeBlockElement extends win.HTMLElement {

        /**
         * @type {?SimpleEditorInterface}
         */
        editor = null;

        constructor() {
            super();
            this.attachShadow({mode: 'open'});

            const stylesToCopy = document.head.querySelectorAll('link[rel="stylesheet"]:not([media="print"]),style');
            const copiedStyles = Array.from(stylesToCopy).map(styleEl => styleEl.cloneNode(true));

            const cmContainer = document.createElement('div');
            cmContainer.style.pointerEvents = 'none';
            cmContainer.contentEditable = 'false';
            cmContainer.classList.add('CodeMirrorContainer');
            cmContainer.classList.toggle('dark-mode', document.documentElement.classList.contains('dark-mode'));

            this.shadowRoot.append(...copiedStyles, cmContainer);
        }

        getLanguage() {
            const getLanguageFromClassList = classes => {
                const langClasses = classes.split(' ').filter(cssClass => cssClass.startsWith('language-'));
                return (langClasses[0] || '').replace('language-', '');
            };

            const code = this.querySelector('code');
            const pre = this.querySelector('pre');
            return getLanguageFromClassList(pre.className) || (code && getLanguageFromClassList(code.className)) || '';
        }

        setContent(content, language) {
            if (this.editor) {
                this.editor.setContent(content);
                this.editor.setMode(language, content);
            }

            let pre = this.querySelector('pre');
            if (!pre) {
                pre = doc.createElement('pre');
                this.append(pre);
            }
            pre.innerHTML = '';

            const code = doc.createElement('code');
            pre.append(code);
            code.innerText = content;
            code.className = `language-${language}`;
        }

        getContent() {
            const code = this.querySelector('code') || this.querySelector('pre');
            const tempEl = document.createElement('pre');
            tempEl.innerHTML = code.innerHTML.replace(/\ufeff/g, '');

            const brs = tempEl.querySelectorAll('br');
            for (const br of brs) {
                br.replaceWith('\n');
            }

            return tempEl.textContent;
        }

        connectedCallback() {
            const connectedTime = Date.now();
            if (this.editor) {
                return;
            }

            this.cleanChildContent();
            const content = this.getContent();
            const lines = content.split('\n').length;
            const height = (lines * 19.2) + 18 + 24;
            this.style.height = `${height}px`;

            const container = this.shadowRoot.querySelector('.CodeMirrorContainer');
            const renderEditor = Code => {
                this.editor = Code.wysiwygView(container, this.shadowRoot, content, this.getLanguage());
                setTimeout(() => {
                    this.style.height = null;
                }, 12);
            };

            window.importVersioned('code').then(Code => {
                const timeout = (Date.now() - connectedTime < 20) ? 20 : 0;
                setTimeout(() => renderEditor(Code), timeout);
            });
        }

        cleanChildContent() {
            const pre = this.querySelector('pre');
            if (!pre) return;

            for (const preChild of pre.childNodes) {
                if (preChild.nodeName === '#text' && preChild.textContent === '﻿') {
                    preChild.remove();
                }
            }
        }

    }

    win.customElements.define('code-block', CodeBlockElement);
}

/**
 * @param {Editor} editor
 */
function register(editor) {
    editor.ui.registry.addIcon('codeblock', '<svg width="24" height="24"><path d="M4 3h16c.6 0 1 .4 1 1v16c0 .6-.4 1-1 1H4a1 1 0 0 1-1-1V4c0-.6.4-1 1-1Zm1 2v14h14V5Z"/><path d="M11.103 15.423c.277.277.277.738 0 .922a.692.692 0 0 1-1.106 0l-4.057-3.78a.738.738 0 0 1 0-1.107l4.057-3.872c.276-.277.83-.277 1.106 0a.724.724 0 0 1 0 1.014L7.6 12.012ZM12.897 8.577c-.245-.312-.2-.675.08-.955.28-.281.727-.27 1.027.033l4.057 3.78a.738.738 0 0 1 0 1.107l-4.057 3.872c-.277.277-.83.277-1.107 0a.724.724 0 0 1 0-1.014l3.504-3.412z"/></svg>');

    editor.ui.registry.addButton('codeeditor', {
        tooltip: 'Insert code block',
        icon: 'codeblock',
        onAction() {
            editor.execCommand('codeeditor');
        },
    });

    editor.ui.registry.addButton('editcodeeditor', {
        tooltip: 'Edit code block',
        icon: 'edit-block',
        onAction() {
            editor.execCommand('codeeditor');
        },
    });

    editor.addCommand('codeeditor', () => {
        const selectedNode = editor.selection.getNode();
        const doc = selectedNode.ownerDocument;
        if (elemIsCodeBlock(selectedNode)) {
            showPopupForCodeBlock(editor, selectedNode);
        } else {
            const textContent = editor.selection.getContent({format: 'text'});
            const direction = document.dir === 'rtl' ? 'ltr' : '';
            showPopup(editor, textContent, '', direction, (newCode, newLang) => {
                const pre = doc.createElement('pre');
                const code = doc.createElement('code');
                code.classList.add(`language-${newLang}`);
                code.innerText = newCode;
                if (direction) {
                    pre.setAttribute('dir', direction);
                }

                pre.append(code);
                editor.insertContent(pre.outerHTML);
            });
        }
    });

    editor.on('dblclick', () => {
        const selectedNode = editor.selection.getNode();
        if (elemIsCodeBlock(selectedNode)) {
            showPopupForCodeBlock(editor, selectedNode);
        }
    });

    editor.on('PreInit', () => {
        editor.parser.addNodeFilter('pre', elms => {
            for (const el of elms) {
                const wrapper = window.tinymce.html.Node.create('code-block', {
                    contenteditable: 'false',
                });

                const childCodeBlock = el.children().filter(child => child.name === 'code')[0] || null;
                const direction = el.attr('dir') || (childCodeBlock && childCodeBlock.attr('dir')) || '';
                if (direction) {
                    wrapper.attr('dir', direction);
                }

                const spans = el.getAll('span');
                for (const span of spans) {
                    span.unwrap();
                }
                el.attr('style', null);
                el.wrap(wrapper);
            }
        });

        editor.parser.addNodeFilter('code-block', elms => {
            for (const el of elms) {
                el.attr('contenteditable', 'false');
            }
        });

        editor.serializer.addNodeFilter('code-block', elms => {
            for (const el of elms) {
                const direction = el.attr('dir');
                if (direction && el.firstChild) {
                    el.firstChild.attr('dir', direction);
                } else if (el.firstChild) {
                    el.firstChild.attr('dir', null);
                }

                el.unwrap();
            }
        });
    });

    editor.ui.registry.addContextToolbar('codeeditor', {
        predicate(node) {
            return node.nodeName.toLowerCase() === 'code-block';
        },
        items: 'editcodeeditor',
        position: 'node',
        scope: 'node',
    });

    editor.on('PreInit', () => {
        defineCodeBlockCustomElement(editor);
    });
}

/**
 * @return {register}
 */
export function getPlugin() {
    return register;
}
