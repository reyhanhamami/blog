import Swiper from 'swiper';
import { Navigation } from 'swiper/modules';

let featuredSwiper;

function mountFeaturedSlider() {
    const element = document.querySelector('[data-featured-swiper]');

    if (!element || element.querySelectorAll('.swiper-slide').length < 2 || featuredSwiper) return;

    featuredSwiper = new Swiper(element, {
        modules: [Navigation],
        slidesPerView: 1,
        spaceBetween: 0,
        loop: false,
        navigation: {
            prevEl: '[data-featured-prev]',
            nextEl: '[data-featured-next]',
        },
    });
}

function unmountFeaturedSlider() {
    featuredSwiper?.destroy(true, true);
    featuredSwiper = undefined;
}

document.addEventListener('DOMContentLoaded', mountFeaturedSlider);
document.addEventListener('livewire:navigated', mountFeaturedSlider);
document.addEventListener('livewire:navigating', unmountFeaturedSlider);
