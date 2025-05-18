import { createApp, h } from 'vue'
import { createInertiaApp, Link } from '@inertiajs/vue3'
import ui from '@nuxt/ui/vue-plugin'
import Layout from './Layouts/Layout.vue'

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
