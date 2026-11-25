import { defineConfig } from '#q-app/wrappers';
import tailwindcss from '@tailwindcss/vite';
import { fileURLToPath } from 'node:url';

export default defineConfig(() => ({
  boot: ['api', 'theme'],

  css: ['app.css'],

  build: {
    target: {
      browser: ['es2022', 'firefox115', 'chrome115', 'safari15'],
      node: 'node22',
    },

    typescript: {
      strict: true,
      vueShim: true,
    },

    vueRouterMode: 'history',

    env: {
      API_URL: process.env.API_URL ?? 'http://localhost:8000',
    },

    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },

    extendViteConf(viteConf) {
      viteConf.plugins = [...(viteConf.plugins ?? []), tailwindcss()];
    },
  },

  devServer: {
    open: false,
  },

  framework: {
    config: {},
    plugins: [],
  },

  animations: [],
}));
