import { fileURLToPath } from 'node:url';
import vue from '@vitejs/plugin-vue';
import { defineConfig } from 'vite-plus';

export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            // Lets a stub import the package by name, as a host would.
            '@xelqun/panels': fileURLToPath(new URL('./resources/js/index.ts', import.meta.url)),
        },
    },
    test: {
        environment: 'happy-dom',
        include: ['tests/js/**/*.test.ts'],
        // Forms and tables ship TS and Vue source, so they have to go through Vite.
        server: { deps: { inline: ['@xelqun/forms', '@xelqun/tables'] } },
    },
    lint: {
        ignorePatterns: ['vendor/**', 'node_modules/**'],
        options: {
            denyWarnings: true,
            typeAware: true,
        },
    },
    fmt: {
        printWidth: 100,
        tabWidth: 4,
        singleQuote: true,
        semi: true,
        singleAttributePerLine: false,
        htmlWhitespaceSensitivity: 'css',
        ignorePatterns: [
            'vendor/**',
            '.claude/**',
            'composer.json',
            'docs/**',
            'tests/Fixtures/**',
        ],
        sortTailwindcss: {
            functions: ['clsx', 'cn', 'cva'],
            stylesheet: 'resources/css/xelqun-panels.css',
        },
    },
});
