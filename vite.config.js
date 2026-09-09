import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                // Sitio público: entrada aparte para que la app no cargue estilos de landing.
                'resources/css/marketing.css',
                'resources/js/app.js',
                'resources/js/charts.js',
            ],
            refresh: true,
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
