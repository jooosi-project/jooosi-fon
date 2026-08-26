import { fileURLToPath, URL } from 'node:url';

import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import { wordpress, wordpressExternals } from '@nabasa/vp-wp';
import { defineConfig } from 'vite-plus';
import svgr from 'vite-plugin-svgr';

export default defineConfig({
    plugins: [
        react(),
        svgr({
            svgrOptions: {
                dimensions: false,
            },
        }),
        tailwindcss(),
        wordpress({
            entry: {
                app: 'resources/main.tsx',
            },
            outDir: 'assets/dist',
            sourcemap: false,
        }),
        wordpressExternals({ preset: 'wordpress' }),
    ],
    css: {
        lightningcss: true,
    },
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources', import.meta.url)),
        },
    },
    build: {
        sourcemap: false,
    },
});
