/**
 * Hero Carousel
 * Fade-based slide transitions, dot navigation, auto-play, swipe support.
 */

class HeroCarousel {
  constructor(el, opts = {}) {
    this.root     = el;
    this.slides   = Array.from(el.querySelectorAll('[data-carousel-slide]'));
    this.dotsNav  = el.querySelector('[data-carousel-dots]');
    this.dots     = this.dotsNav
      ? Array.from(this.dotsNav.querySelectorAll('[data-carousel-dot]'))
      : [];

    this.current     = 0;
    this.total       = this.slides.length;
    this.isAnimating = false;
    this.autoPlay    = opts.autoPlay ?? true;
    this.interval    = opts.interval ?? 5000;
    this.timer       = null;

    if (this.total < 2) return;

    this._bindDots();
    this._bindVisibility();
    this._bindTouch();

    if (this.autoPlay) this._startTimer();
  }

  goTo(index) {
    if (this.isAnimating || index === this.current) return;

    this.isAnimating = true;

    this.slides[this.current].classList.remove('is-active');
    this.slides[this.current].setAttribute('aria-current', 'false');
    if (this.dots[this.current]) {
      this.dots[this.current].classList.remove('is-active');
      this.dots[this.current].setAttribute('aria-current', 'false');
    }

    this.current = (index + this.total) % this.total;

    this.slides[this.current].classList.add('is-active');
    this.slides[this.current].setAttribute('aria-current', 'true');
    if (this.dots[this.current]) {
      this.dots[this.current].classList.add('is-active');
      this.dots[this.current].setAttribute('aria-current', 'true');
    }

    setTimeout(() => { this.isAnimating = false; }, 650);
  }

  next() { this.goTo(this.current + 1); }
  prev() { this.goTo(this.current - 1); }

  _startTimer() {
    this.timer = setInterval(() => this.next(), this.interval);
  }

  _stopTimer() {
    clearInterval(this.timer);
    this.timer = null;
  }

  _bindDots() {
    this.dots.forEach((dot) => {
      dot.addEventListener('click', () => {
        const index = parseInt(dot.dataset.carouselDot, 10);
        this._stopTimer();
        this.goTo(index);
        if (this.autoPlay) this._startTimer();
      });
    });
  }

  _bindVisibility() {
    document.addEventListener('visibilitychange', () => {
      if (document.hidden) {
        this._stopTimer();
      } else if (this.autoPlay) {
        this._startTimer();
      }
    });
  }

  _bindTouch() {
    let startX = 0;
    const threshold = 50;

    this.root.addEventListener('touchstart', (e) => {
      startX = e.touches[0].clientX;
    }, { passive: true });

    this.root.addEventListener('touchend', (e) => {
      const delta = e.changedTouches[0].clientX - startX;
      if (Math.abs(delta) < threshold) return;
      this._stopTimer();
      if (delta < 0) this.next();
      else this.prev();
      if (this.autoPlay) this._startTimer();
    }, { passive: true });
  }
}

document.querySelectorAll('[data-carousel]').forEach((el) => {
  new HeroCarousel(el, { autoPlay: true, interval: 5000 });
});

export default HeroCarousel;
