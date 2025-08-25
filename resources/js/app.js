import { createApp, h } from 'vue'
import { createInertiaApp, Link } from '@inertiajs/vue3'
import ui from '@nuxt/ui/vue-plugin'
import { configureEcho } from '@laravel/echo-vue'
import Layout from './Layouts/Layout.vue'

configureEcho({
    broadcaster: 'reverb'
    // key: import.meta.env.VITE_REVERB_APP_KEY,
    // wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
    // wsPort: import.meta.env.VITE_REVERB_PORT || 8080,
    // wssPort: import.meta.env.VITE_REVERB_PORT || 8080,
    // forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    // enabledTransports: ['ws', 'wss'],
    // disableStats: true,
    // debug: true
})
createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true })
        let page = pages[`./Pages/${name}.vue`]
        page.default.layout = page.default.layout || Layout
        return page
    },
    setup ({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ui)
            .component('Link', Link)
            .mount(el)
    }
})
