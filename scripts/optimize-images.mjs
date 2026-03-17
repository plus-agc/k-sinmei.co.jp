/**
 * ビルド後の dist/ 内画像を最適化するスクリプト
 *
 * 処理内容:
 *   1. JPG/PNG → AVIF・WebP 版を生成
 *   2. 元の JPG/PNG も品質を落として圧縮（フォールバック用）
 *   3. dist/ の HTML を走査し、<img src="*.jpg/png"> を <picture> タグに変換
 *   4. 既に <picture> 内にある <img> は処理しない
 *   5. CSS background-image は変換しない（圧縮済み JPG をそのまま使用）
 */
import sharp from 'sharp';
import { readdir, readFile, writeFile, stat, rename, unlink } from 'fs/promises';
import { join, extname, dirname, relative } from 'path';
import { fileURLToPath } from 'url';

// JPEG/PNG 最適化設定
const MAX_WIDTH   = 2560;
const JPEG_QUALITY = 82;
const AVIF_QUALITY = 65;
const WEBP_QUALITY = 80;

/** バイト数を人間が読みやすい形式に変換 */
function fmt(bytes) {
  if (bytes >= 1024 * 1024) return (bytes / 1024 / 1024).toFixed(1) + 'MB';
  return Math.round(bytes / 1024) + 'KB';
}

// ─── 画像ファイルの最適化 ────────────────────────────────────────────

/**
 * JPEG を圧縮 + AVIF/WebP 版を生成
 * @param {string} filePath
 */
async function processJpeg(filePath) {
  const before = (await stat(filePath)).size;
  const tmp = filePath + '.opt.tmp';
  try {
    const img = sharp(filePath).resize({ width: MAX_WIDTH, withoutEnlargement: true });

    // AVIF 生成
    await img.clone().avif({ quality: AVIF_QUALITY }).toFile(filePath.replace(/\.jpe?g$/i, '.avif'));
    // WebP 生成
    await img.clone().webp({ quality: WEBP_QUALITY }).toFile(filePath.replace(/\.jpe?g$/i, '.webp'));
    // 元 JPEG を圧縮（フォールバック）
    await img.clone().jpeg({ quality: JPEG_QUALITY, mozjpeg: true }).toFile(tmp);

    await unlink(filePath);
    await rename(tmp, filePath);

    const after = (await stat(filePath)).size;
    const saved = Math.round((1 - after / before) * 100);
    console.log(`  [JPEG] ${shortPath(filePath)}: ${fmt(before)} → ${fmt(after)} (-${saved}%) + .avif/.webp 生成`);
  } catch (e) {
    try { await unlink(tmp); } catch {}
    console.error(`  [JPEG] エラー: ${shortPath(filePath)}: ${e.message}`);
  }
}

/**
 * PNG を圧縮 + AVIF/WebP 版を生成
 * 最適化後に元より大きくなった場合は PNG 圧縮をスキップ
 * @param {string} filePath
 */
async function processPng(filePath) {
  const before = (await stat(filePath)).size;
  const tmp = filePath + '.opt.tmp';
  try {
    const img = sharp(filePath).resize({ width: MAX_WIDTH, withoutEnlargement: true });

    // AVIF 生成
    await img.clone().avif({ quality: AVIF_QUALITY }).toFile(filePath.replace(/\.png$/i, '.avif'));
    // WebP 生成
    await img.clone().webp({ quality: WEBP_QUALITY, lossless: false }).toFile(filePath.replace(/\.png$/i, '.webp'));
    // 元 PNG を圧縮（フォールバック）
    await img.clone().png({ compressionLevel: 9, effort: 10 }).toFile(tmp);

    const after = (await stat(tmp)).size;
    if (after >= before) {
      await unlink(tmp);
      console.log(`  [PNG]  ${shortPath(filePath)}: スキップ（${fmt(after)} > ${fmt(before)}）+ .avif/.webp 生成`);
    } else {
      await unlink(filePath);
      await rename(tmp, filePath);
      const saved = Math.round((1 - after / before) * 100);
      console.log(`  [PNG]  ${shortPath(filePath)}: ${fmt(before)} → ${fmt(after)} (-${saved}%) + .avif/.webp 生成`);
    }
  } catch (e) {
    try { await unlink(tmp); } catch {}
    console.error(`  [PNG]  エラー: ${shortPath(filePath)}: ${e.message}`);
  }
}

/** ファイルパスを短く表示（最後の2セグメント） */
function shortPath(p) {
  return p.split('/').slice(-2).join('/');
}

/**
 * ディレクトリ内の画像を再帰的に処理
 * @param {string} dirPath
 */
async function processImagesDir(dirPath) {
  let entries;
  try {
    entries = await readdir(dirPath, { withFileTypes: true });
  } catch {
    return;
  }
  for (const entry of entries) {
    const fullPath = join(dirPath, entry.name);
    if (entry.isDirectory()) {
      await processImagesDir(fullPath);
    } else {
      const ext = extname(entry.name).toLowerCase();
      if (['.jpg', '.jpeg'].includes(ext)) await processJpeg(fullPath);
      else if (ext === '.png')              await processPng(fullPath);
    }
  }
}

// ─── HTML の <img> → <picture> 変換 ────────────────────────────────

/**
 * HTML テキスト内の <img src="*.jpg/png"> を <picture> タグに変換
 * - 既に <picture> 内にある <img> は処理しない
 * - SVG・WebP・AVIF は対象外
 * @param {string} html
 * @returns {string}
 */
function convertImgToPicture(html) {
  // 既存の <picture>...</picture> をプレースホルダーに退避
  const pictures = [];
  const withPlaceholders = html.replace(/<picture[\s\S]*?<\/picture>/gi, (match) => {
    const idx = pictures.length;
    pictures.push(match);
    return `__PICTURE_PLACEHOLDER_${idx}__`;
  });

  // JPG/PNG の <img> を <picture> に変換（ローカルパスのみ、外部URLは除外）
  const converted = withPlaceholders.replace(
    /<img(\s[^>]*?)src=(["'])((?:\.{0,2}\/)[^"']*?[^"']+\.(jpe?g|png))\2([^>]*?)(\s*\/?>)/gi,
    (match, before, quote, src, ext, after, closing) => {
      // avif/webp のパスを src から生成
      const avifSrc = src.replace(/\.(jpe?g|png)$/i, '.avif');
      const webpSrc = src.replace(/\.(jpe?g|png)$/i, '.webp');
      // 元の <img> タグを再構成（属性はそのまま）
      const imgTag = `<img${before}src=${quote}${src}${quote}${after}${closing}`;
      // display:contents で <picture> 自体をレイアウトから除外し、
      // <img> が直接 flex/grid アイテムとして振る舞うようにする
      return `<picture style="display:contents"><source srcset="${avifSrc}" type="image/avif"><source srcset="${webpSrc}" type="image/webp">${imgTag}</picture>`;
    }
  );

  // プレースホルダーを元の <picture> タグに戻す
  return converted.replace(/__PICTURE_PLACEHOLDER_(\d+)__/g, (_, idx) => pictures[idx]);
}

/**
 * dist/ 内の HTML ファイルを再帰的に処理
 * @param {string} dirPath
 */
async function processHtmlDir(dirPath) {
  let entries;
  try {
    entries = await readdir(dirPath, { withFileTypes: true });
  } catch {
    return;
  }
  for (const entry of entries) {
    const fullPath = join(dirPath, entry.name);
    if (entry.isDirectory()) {
      await processHtmlDir(fullPath);
    } else if (entry.name.endsWith('.html')) {
      const original = await readFile(fullPath, 'utf-8');
      const converted = convertImgToPicture(original);
      if (converted !== original) {
        await writeFile(fullPath, converted, 'utf-8');
        console.log(`  [HTML] ${shortPath(fullPath)}: <img> → <picture> 変換済み`);
      }
    }
  }
}

// ─── エントリーポイント ──────────────────────────────────────────────

/**
 * @param {URL} distDir - astro:build:done の dir
 */
export async function optimizeImages(distDir) {
  const distPath = fileURLToPath(distDir);
  const imagesDir = join(distPath, 'assets', 'images');

  console.log('\n[1/2] 画像を最適化（AVIF・WebP 生成）...');
  await processImagesDir(imagesDir);

  console.log('\n[2/2] HTML の <img> を <picture> タグに変換...');
  await processHtmlDir(distPath);

  console.log('\n画像最適化が完了しました。\n');
}
