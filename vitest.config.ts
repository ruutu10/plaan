import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

/**
 * Standalone of `vite.config.ts` on purpose: the build config loads the
 * Laravel, Wayfinder and Tailwind plugins, which shell out to artisan and are
 * not wanted when running unit tests.
 */
export default defineConfig({
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        include: ['resources/js/**/*.test.ts'],
        environment: 'node',
    },
});
