import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, loadEnv } from 'vite';

export default defineConfig(({ mode }) => {
    // Empty prefix so APP_VERSION (not VITE_-prefixed) is picked up too, from
    // either .env or the actual process env (e.g. set by CI before the build).
    const env = loadEnv(mode, process.cwd(), '');

    return {
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.ts'],
                refresh: true,
                fonts: [
                    bunny('Instrument Sans', {
                        weights: [400, 500, 600],
                    }),
                ],
            }),
            inertia(),
            tailwindcss(),
            vue({
                template: {
                    transformAssetUrls: {
                        base: null,
                        includeAbsolute: false,
                    },
                },
            }),
            wayfinder({
                formVariants: true,
            }),
        ],
        define: {
            // Ties the Sentry release to the app version unless a release is
            // explicitly set, so the two never drift apart.
            'import.meta.env.VITE_SENTRY_RELEASE': JSON.stringify(
                env.VITE_SENTRY_RELEASE || env.APP_VERSION || '',
            ),
        },
    };
});
