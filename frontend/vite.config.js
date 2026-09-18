import { fileURLToPath, URL } from "node:url";

import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import vueDevTools from "vite-plugin-vue-devtools";
import { VitePWA } from "vite-plugin-pwa";

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    // vueDevTools(),
    VitePWA({
      registerType: "autoUpdate",
      // Aktif juga pas `npm run dev`, biar bisa dites tanpa build produksi.
      devOptions: {
        enabled: true,
      },
      // API request selalu harus fresh dari server (data patroli real-time),
      // jadi cuma app shell (JS/CSS/HTML) yang di-precache, bukan response API.
      workbox: {
        navigateFallbackDenylist: [/^\/api\//],
      },
      manifest: {
        name: "Sistem Monitoring Keamanan",
        short_name: "SiMonKeamanan",
        description: "Aplikasi monitoring dan patroli keamanan.",
        start_url: "/",
        scope: "/",
        display: "standalone",
        background_color: "#1f2454",
        theme_color: "#1f2454",
        icons: [
          {
            src: "/icons/icon-192.png",
            sizes: "192x192",
            type: "image/png",
          },
          {
            src: "/icons/icon-512.png",
            sizes: "512x512",
            type: "image/png",
          },
          {
            src: "/icons/maskable-icon-512.png",
            sizes: "512x512",
            type: "image/png",
            purpose: "maskable",
          },
        ],
      },
    }),
  ],
  resolve: {
    alias: {
      "@": fileURLToPath(new URL("./src", import.meta.url)),
    },
  },
});
