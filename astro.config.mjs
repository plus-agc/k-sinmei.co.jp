import { defineConfig } from "astro/config";
import relativeLinks from "astro-relative-links";

import sitemap from "@astrojs/sitemap";

// https://astro.build/config
export default defineConfig({
  site: "https://www.k-sinmei.co.jp/",
  integrations: [relativeLinks(), sitemap()],
  compressHTML: false,
  build: {
    assets: 'assets',
    // Astro画像最適化のハッシュを削除
    assetsPrefix: undefined
  },
  image: {
    // 画像最適化を無効化（ハッシュなしの静的配信）
    service: {
      entrypoint: 'astro/assets/services/noop'
    }
  },
  vite: {
    build: {
      cssCodeSplit: false,  // CSSを1ファイルにまとめる
      rollupOptions: {
        output: {
          // JSファイル → assets/js/
          entryFileNames: 'assets/js/[name].js',
          chunkFileNames: 'assets/js/[name].js',
          // CSS・その他アセットを種類別に分類
          assetFileNames: (assetInfo) => {
            const name = assetInfo.name || '';
            // CSSファイル → style.css
            if (name.endsWith('.css')) {
              return 'assets/css/style.css';
            }
            // 画像ファイル
            if (/\.(png|jpe?g|gif|svg|webp|avif|ico)$/i.test(name)) {
              return 'assets/images/[name][extname]';
            }
            // フォントファイル
            if (/\.(woff2?|eot|ttf|otf)$/i.test(name)) {
              return 'assets/fonts/[name][extname]';
            }
            // その他
            return 'assets/[name][extname]';
          }
        }
      }
    }
  }
});
