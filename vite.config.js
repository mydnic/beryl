import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import laravel from 'laravel-vite-plugin'
import tailwindcss from '@tailwindcss/vite'
import ui from '@nuxt/ui/vite'

export default defineConfig({
    plugins: [
        vue(),
        ui({
            inertia: true,
            ui: {
                colors: {
                    primary: 'lime',
                    neutral: 'slate'
                }
            },
            autoImport: {
                imports: ['vue']
            },
            components: {
                dirs: ['resources/js/Components']
            }
        }),
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true
        }),
        tailwindcss()
    ]
})
