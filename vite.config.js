import { resolve } from 'node:path';
import { defineConfig } from 'vite';

export default defineConfig({
  publicDir: false,

  build: {
    outDir: resolve(import.meta.dirname, 'public/assets/editor'),
    emptyOutDir: true,
    sourcemap: false,

    lib: {
      entry: resolve(import.meta.dirname, 'resources/editor/index.js'),
      formats: ['es'],
      fileName: () => 'erased-editor.js',
    },
  },
});
