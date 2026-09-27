import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/css/public.css', 'resources/js/app.js', 'resources/js/public-base.js', 'resources/js/public-home.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
