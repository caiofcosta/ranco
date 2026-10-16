import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: { include: ['tests/JavaScript/**/*.test.js'], environment: 'node' },
});
