import path from 'node:path'
import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vite'

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
    port: 8100,
    strictPort: true,
    fs: {
      allow: [path.resolve(__dirname, '..')],
    },
  },
})
