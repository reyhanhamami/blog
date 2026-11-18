import Swiper from 'swiper';
import { Autoplay, Navigation } from 'swiper/modules';
import 'swiper/css';

let featuredSlider;

function mountFeaturedSlider() {
    const element = document.querySelector('[data-featured-swiper]');
    if (featuredSlider?.element === element && !featuredSlider.swiper.destroyed) return;
    unmountFeaturedSlider();
    if (!element || element.querySelectorAll('.swiper-slide').length < 2) return;

    const region = element.closest('[data-featured-carousel]');
    const toggle = region.querySelector('[data-featured-toggle]');
    const motion = matchMedia('(prefers-reduced-motion: reduce)');
    const abort = new AbortController();
    const listen = (target, event, callback, options = {}) => target.addEventListener(event, callback, { ...options, signal: abort.signal });
    let manuallyPaused = false;
    let hovering = false;
    let pressed = false;
    let disposed = false;

    const swiper = new Swiper(element, {
        modules: [Navigation, Autoplay],
        slidesPerView: 1,
        spaceBetween: 0,
        autoHeight: true,
        speed: motion.matches ? 0 : 600,
        loop: false,
        rewind: true,
        threshold: 10,
        // Livewire starts on mousedown, which a cancelled pointerdown suppresses.
        touchStartPreventDefault: false,
        preventClicks: true,
        preventClicksPropagation: true,
        autoplay: {
            enabled: !motion.matches,
            delay: 5000,
            disableOnInteraction: false,
            pauseOnMouseEnter: true,
            stopOnLastSlide: false,
        },
        navigation: {
            prevEl: region.querySelector('[data-featured-prev]'),
            nextEl: region.querySelector('[data-featured-next]'),
        },
    });

    function syncAutoplay() {
        if (disposed || swiper.destroyed) return;
        const blocked = manuallyPaused || motion.matches || document.hidden || hovering || pressed || region.contains(document.activeElement);
        if (blocked) {
            if (swiper.autoplay.running) swiper.autoplay.stop();
        } else if (!swiper.autoplay.running) {
            swiper.autoplay.start();
            if (swiper.autoplay.paused) swiper.autoplay.resume();
        }
        toggle.hidden = motion.matches;
        toggle.setAttribute('aria-pressed', String(manuallyPaused));
        toggle.setAttribute('aria-label', manuallyPaused ? 'Lanjutkan pergantian artikel otomatis' : 'Jeda pergantian artikel otomatis');
        toggle.querySelector('span').textContent = manuallyPaused ? '▶' : 'Ⅱ';
        swiper.wrapperEl.setAttribute('aria-live', blocked ? 'polite' : 'off');
    }

    function syncSlides() {
        swiper.slides.forEach((slide, index) => {
            const inactive = index !== swiper.activeIndex;
            slide.inert = inactive;
            slide.setAttribute('aria-hidden', String(inactive));
        });
    }

    // Livewire navigates on mouseup, before Swiper's click suppression can run.
    listen(element, 'mouseup', event => {
        if (event.target.closest('a[href]') && !swiper.allowClick) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }, { capture: true });
    listen(region, 'pointerenter', event => {
        if (event.pointerType !== 'mouse') return;
        hovering = true;
        syncAutoplay();
    });
    listen(region, 'pointerleave', event => {
        if (event.pointerType !== 'mouse') return;
        hovering = false;
        syncAutoplay();
    });
    listen(region, 'pointerdown', () => { pressed = true; syncAutoplay(); }, { capture: true });
    const release = () => { pressed = false; syncAutoplay(); };
    listen(document, 'pointerup', release);
    listen(document, 'pointercancel', release);
    listen(region, 'focusin', syncAutoplay);
    listen(region, 'focusout', () => queueMicrotask(syncAutoplay));
    listen(document, 'visibilitychange', syncAutoplay);
    listen(motion, 'change', () => {
        swiper.params.speed = motion.matches ? 0 : 600;
        syncAutoplay();
    });
    listen(toggle, 'click', () => { manuallyPaused = !manuallyPaused; syncAutoplay(); });
    swiper.on('slideChange', syncSlides);
    // One native resume must not override another pause reason (focus/user pause).
    swiper.on('autoplayStart autoplayResume', syncAutoplay);
    syncSlides();
    syncAutoplay();

    featuredSlider = { element, swiper, dispose() {
        disposed = true;
        abort.abort();
        swiper.slides.forEach(slide => { slide.inert = false; slide.removeAttribute('aria-hidden'); });
        swiper.wrapperEl.removeAttribute('aria-live');
        toggle.hidden = true;
        swiper.destroy(true, true);
    } };
}

function unmountFeaturedSlider() {
    featuredSlider?.dispose();
    featuredSlider = undefined;
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mountFeaturedSlider, { once: true });
else mountFeaturedSlider();
document.addEventListener('livewire:navigated', mountFeaturedSlider);
document.addEventListener('livewire:navigating', unmountFeaturedSlider);
