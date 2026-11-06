import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// The panel is mounted at a secret path on support.shreewin06.site.
// HashRouter is used inside, so the server only ever serves the root — but
// asset URLs still need this base prefix to resolve through nginx.
export default defineConfig({
  base: '/bdgscriptwala@admin/',
  plugins: [react()],
  build: {
    outDir: '/raxi/support/admin/build',
    emptyOutDir: true,
    chunkSizeWarningLimit: 1500,
  },
})
