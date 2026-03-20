import path from 'node:path'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// https://vite.dev/config/
export default defineConfig({
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
      '@contracts': path.resolve(__dirname, '../packages/contracts/generated'),
    },
  },
  plugins: [vue()],
  server: {
    fs: {
      allow: [path.resolve(__dirname, '..')],
    },
  },
})
