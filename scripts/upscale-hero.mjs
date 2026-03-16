/**
 * ヒーロー画像をフルHD幅（1920px）に高品質アップスケール
 * Lanczos3 でリサンプリング
 */
import sharp from "sharp";
import { fileURLToPath } from "url";
import path from "path";

import fs from "fs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, "..");
const input = path.join(root, "public/assets/images/top-hero.png");
const outputTmp = path.join(root, "public/assets/images/top-hero.tmp.png");

const TARGET_WIDTH = 1920;

const meta = await sharp(input).metadata();
const scale = TARGET_WIDTH / meta.width;
const height = Math.round(meta.height * scale);

await sharp(input)
  .resize({ width: TARGET_WIDTH, height, kernel: sharp.kernel.lanczos3 })
  .png({ quality: 95, compressionLevel: 6 })
  .toFile(outputTmp);

fs.renameSync(outputTmp, input);

console.log(`Upscaled: ${meta.width}x${meta.height} → ${TARGET_WIDTH}x${height}`);
console.log(`Saved: ${input}`);
