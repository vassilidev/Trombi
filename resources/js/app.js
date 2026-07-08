import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

const appName = import.meta.env.VITE_APP_NAME || 'Trombi';

createInertiaApp({
    title: (title) => (title ? `${title} — ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) }).use(plugin);

        // Ferme un élément quand on clique en dehors : v-click-outside="() => open = false"
        app.directive('click-outside', {
            mounted(element, binding) {
                element.__clickOutside = (event) => {
                    if (!(element === event.target || element.contains(event.target))) {
                        binding.value(event);
                    }
                };
                document.addEventListener('mousedown', element.__clickOutside);
            },
            unmounted(element) {
                document.removeEventListener('mousedown', element.__clickOutside);
            },
        });

        app.mount(el);
    },
    progress: {
        color: '#1e2bd1',
    },
});
