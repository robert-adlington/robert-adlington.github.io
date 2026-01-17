import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath, URL } from 'node:url'

export default defineConfig({
  base: '/projects/cribbage/dist/',
  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url))
    }
  },
  build: {
    outDir: '../dist',
    emptyOutDir: true,
    rollupOptions: {
      output: {
        manualChunks: undefined
      }
    }
  },
  server: {
    port: 5174,
    proxy: {
      '/api': {
        target: 'http://localhost',
        changeOrigin: true
      },
      '/projects/cribbage/api': {
        target: 'http://localhost',
        changeOrigin: true
      }
    }
  }
})
