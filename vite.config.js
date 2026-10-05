import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/admin.css', 'resources/js/admin.js', 'resources/css/public.css', 'resources/js/public.js', 'resources/js/public-home.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
