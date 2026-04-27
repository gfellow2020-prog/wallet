import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        // Listen on all interfaces so other devices on the LAN can use the dev server.
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        // Omit `https` so Laravel Vite can use Herd/Valet TLS (https://localhost:5173).
        // If you have no Herd/Valet, set `https: false` and open http://localhost:5173 only.
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
