import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue'
import inertia from '@inertiajs/vite'

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true
        }),
        tailwindcss(),
        vue(),
        inertia({
            ssr: false,
        })
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
        warmup: {
            clientFiles: [
                './resources/js/app.js',
                './resources/js/Pages/Home.vue',
                './resources/js/Pages/Layout.vue',
                './resources/js/Pages/Components/*.vue',
            ],
        },
    },
    ssr: {
        noExternal: ['@lucide/vue'],
        external: ['apexcharts', 'vue3-apexcharts'],
    },
    optimizeDeps: {
        include: ['@inertiajs/vue3', '@lucide/vue'],
        exclude: ['apexcharts', 'vue3-apexcharts'],
    },
});