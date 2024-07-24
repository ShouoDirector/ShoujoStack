import {Component} from './component';

export class BackToTop extends Component {

    setup() {
        this.button = this.$el;
        this.targetElem = document.getElementById('header');
        this.showing = false;
        this.breakPoint = 1200;

        if (document.body.classList.contains('flexbox')) {
            this.button.style.display = 'none';
            return;
        }

        this.button.addEventListener('click', this.scrollToTop.bind(this));
        window.addEventListener('scroll', this.onPageScroll.bind(this));
    }

    onPageScroll() {
        const scrollTopPos = document.documentElement.scrollTop || document.body.scrollTop || 0;
        if (!this.showing && scrollTopPos > this.breakPoint) {
            this.button.style.display = 'block';
            this.showing = true;
            setTimeout(() => {
                this.button.style.opacity = 0.4;
            }, 1);
        } else if (this.showing && scrollTopPos < this.breakPoint) {
            this.button.style.opacity = 0;
            this.showing = false;
            setTimeout(() => {
                this.button.style.display = 'none';
            }, 500);
        }
    }

    scrollToTop() {
        const targetTop = this.targetElem.getBoundingClientRect().top;
        const scrollElem = document.documentElement.scrollTop ? document.documentElement : document.body;
        const duration = 300;
        const start = Date.now();
        const scrollStart = this.targetElem.getBoundingClientRect().top;

        function setPos() {
            const percentComplete = (1 - ((Date.now() - start) / duration));
            const target = Math.abs(percentComplete * scrollStart);
            if (percentComplete > 0) {
                scrollElem.scrollTop = target;
                requestAnimationFrame(setPos.bind(this));
            } else {
                scrollElem.scrollTop = targetTop;
            }
        }

        requestAnimationFrame(setPos.bind(this));
    }

}
