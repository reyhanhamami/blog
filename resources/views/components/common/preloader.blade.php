<div
  x-show="loaded"
  x-init="
    const hidePreloader = (delay = 350) => setTimeout(() => loaded = false, delay);

    if (document.readyState === 'loading') {
      window.addEventListener('DOMContentLoaded', hidePreloader, { once: true });
    } else {
      hidePreloader();
    }

    window.addEventListener('livewire:navigated', () => hidePreloader(0), { once: true });
    window.addEventListener('pageshow', () => hidePreloader(0), { once: true });
  "
  class="pointer-events-none fixed left-0 top-0 z-999999 flex h-screen w-screen items-center justify-center bg-white dark:bg-black"
>
  <div
    class="h-16 w-16 animate-spin rounded-full border-4 border-solid border-brand-500 border-t-transparent"
  ></div>
</div>
