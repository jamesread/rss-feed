import { defineConfig } from 'vitest/config';

// Separate from vite.config.mjs so tests don't load the Laravel plugin.
export default defineConfig({
    test: {
        environment: 'node',
        include: ['tests/js/**/*.test.js'],
    },
});
