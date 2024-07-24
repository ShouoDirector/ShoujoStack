import {Component} from './component';

function toggleElem(elem, show) {
    elem.toggleAttribute('hidden', !show);
}

export class PagePicker extends Component {

    setup() {
        this.input = this.$refs.input;
        this.resetButton = this.$refs.resetButton;
        this.selectButton = this.$refs.selectButton;
        this.display = this.$refs.display;
        this.defaultDisplay = this.$refs.defaultDisplay;
        this.buttonSep = this.$refs.buttonSeperator;

        this.selectorEndpoint = this.$opts.selectorEndpoint;

        this.value = this.input.value;
        this.setupListeners();
    }

    setupListeners() {
        this.selectButton.addEventListener('click', this.showPopup.bind(this));
        this.display.parentElement.addEventListener('click', this.showPopup.bind(this));
        this.display.addEventListener('click', e => e.stopPropagation());

        this.resetButton.addEventListener('click', () => {
            this.setValue('', '');
        });
    }

    showPopup() {
        /** @type {EntitySelectorPopup} * */
        const selectorPopup = window.$components.first('entity-selector-popup');
        selectorPopup.show(entity => {
            this.setValue(entity.id, entity.name);
        }, {
            initialValue: '',
            searchEndpoint: this.selectorEndpoint,
            entityTypes: 'page',
            entityPermission: 'view',
        });
    }

    setValue(value, name) {
        this.value = value;
        this.input.value = value;
        this.controlView(name);
    }

    controlView(name) {
        const hasValue = this.value && this.value !== 0;
        toggleElem(this.resetButton, hasValue);
        toggleElem(this.buttonSep, hasValue);
        toggleElem(this.defaultDisplay, !hasValue);
        toggleElem(this.display, hasValue);
        if (hasValue) {
            const id = this.getAssetIdFromVal();
            this.display.textContent = `#${id}, ${name}`;
            this.display.href = window.baseUrl(`/link/${id}`);
        }
    }

    getAssetIdFromVal() {
        return Number(this.value);
    }

}
