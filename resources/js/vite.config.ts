import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import { defineConfig } from 'vite';
import path from 'path';

export default defineConfig({
    plugins: [react(), tailwindcss()],
    build: {
        outDir: path.resolve(__dirname, '../dist'),
        emptyOutDir: true,
        rollupOptions: {
            input: path.resolve(__dirname, 'standalone.tsx'),
            output: {
                entryFileNames: 'studio.js',
                assetFileNames: 'studio.[ext]',
            },
        },
    },
});
