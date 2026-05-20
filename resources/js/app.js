import './bootstrap';
import ApexCharts from 'apexcharts';
import { createPopper } from '@popperjs/core';

// flatpickr
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';
// FullCalendar
import { Calendar } from '@fullcalendar/core';



window.ApexCharts = ApexCharts;
window.createPopper = createPopper;
window.flatpickr = flatpickr;
window.FullCalendar = Calendar;

const initializeOnce = (selector, callback) => {
    const element = document.querySelector(selector);

    if (!element || element.dataset.tailadminInitialized === 'true') {
        return;
    }

    element.dataset.tailadminInitialized = 'true';
    callback();
};

const initializeTailAdminPage = () => {
    initializeOnce('#mapOne', () => {
        import('./components/map').then(module => module.initMap());
    });

    initializeOnce('#chartOne', () => {
        import('./components/chart/chart-1').then(module => module.initChartOne());
    });

    initializeOnce('#chartTwo', () => {
        import('./components/chart/chart-2').then(module => module.initChartTwo());
    });

    initializeOnce('#chartThree', () => {
        import('./components/chart/chart-3').then(module => module.initChartThree());
    });

    initializeOnce('#chartSix', () => {
        import('./components/chart/chart-6').then(module => module.initChartSix());
    });

    initializeOnce('#chartEight', () => {
        import('./components/chart/chart-8').then(module => module.initChartEight());
    });

    initializeOnce('#chartThirteen', () => {
        import('./components/chart/chart-13').then(module => module.initChartThirteen());
    });

    initializeOnce('#calendar', () => {
        import('./components/calendar-init').then(module => module.calendarInit());
    });
};

const setNavigateLoading = (loading) => {
    const content = document.querySelector('[data-page-content]');
    const indicator = document.querySelector('[data-navigate-indicator]');

    content?.classList.toggle('opacity-60', loading);
    content?.classList.toggle('translate-y-1', loading);
    indicator?.classList.toggle('hidden', !loading);
};

document.addEventListener('click', (event) => {
    const link = event.target.closest('a[wire\\:navigate], a[wire\\:navigate\\.hover]');

    if (!link || event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }

    const url = new URL(link.href, window.location.href);

    if (url.origin !== window.location.origin || link.target || link.getAttribute('href')?.startsWith('#')) {
        return;
    }

    if (window.Livewire?.navigate) {
        event.preventDefault();
        window.Livewire.navigate(url.toString());
    }
});

document.addEventListener('DOMContentLoaded', initializeTailAdminPage);
document.addEventListener('livewire:navigate', () => setNavigateLoading(true));
document.addEventListener('livewire:navigating', () => setNavigateLoading(true));
document.addEventListener('livewire:navigated', () => {
    initializeTailAdminPage();

    requestAnimationFrame(() => setNavigateLoading(false));

    if (window.innerWidth < 1280 && window.Alpine?.store('sidebar')) {
        window.Alpine.store('sidebar').setMobileOpen(false);
    }
});
