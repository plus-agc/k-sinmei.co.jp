/**
 * ビルド後の dist/ 内画像を最適化するスクリプト（キャッシュ対応）
 *
 * 処理内容:
 *   1. JPG/PNG → AVIF・WebP 版を生成
 *   2. 元の JPG/PNG も品質を落として圧縮（フォールバック用）
 *   3. dist/ の HTML を走査し、<img src="*.jpg/png"> を <picture> タグに変換
 *   4. 既に <picture> 内にある <img> は処理しない
 *   5. CSS background-image は変換しない（圧縮済み JPG をそのまま使用）
 *
 * キャッシュ:
 *   - .image-cache/ ディレクトリにソース画像のハッシュをキーとして最適化済みファイルを保存
 *   - 次回ビルド時にハッシュが一致すれば sharp をスキップし、キャッシュからコピー
 */
import sharp from 'sharp';
import { readdir, readFile, writeFile, stat, rename, unlink, mkdir, copyFile } from 'fs/promises';
import { join, extname } from 'path';
import { fileURLToPath } from 'url';
import { createHash } from 'crypto';

// JPEG/PNG 最適化設定
const MAX_WIDTH    = 2560;
const JPEG_QUALITY = 82;
const AVIF_QUALITY = 65;
const WEBP_QUALITY = 80;

// キャッシュディレクトリ（プロジェクトルート直下）
const CACHE_DIR           = join(process.cwd(), '.image-cache');
const CACHE_MANIFEST_PATH = join(CACHE_DIR, 'manifest.json');

/** バイト数を人間が読みやすい形式に変換 */
function fmt(bytes) {
  if (bytes >= 1024 * 1024) return (bytes / 1024 / 1024).toFixed(1) + 'MB';
  return Math.round(bytes / 1024) + 'KB';
}

/** ファイルパスを短く表示（最後の2セグメント） */
function shortPath(p) {
  return p.split('/').slice(-2).join('/');
}

/** ファイルの SHA-256 ハッシュを計算（先頭16文字） */
async function hashFile(filePath) {
  const data = await readFile(filePath);
  return createHash('sha256').update(data).digest('hex').slice(0, 16);
}

/** キャッシュマニフェストを読み込む */
async function readManifest() {
  try {
    const data = await readFile(CACHE_MANIFEST_PATH, 'utf-8');
    return JSON.parse(data);
  } catch {
    return {};
  }
}

/** キャッシュマニフェストを保存 */
async function writeManifest(manifest) {
  await mkdir(CACHE_DIR, { recursive: true });
  await writeFile(CACHE_MANIFEST_PATH, JSON.stringify(manifest, null, 2), 'utf-8');
}

// ─── 画像ファイルの最適化 ────────────────────────────────────────────

/**
 * JPEG を圧縮 + AVIF/WebP 版を生成（キャッシュ対応）
 * @param {string} filePath
 * @param {object} manifest
 */
async function processJpeg(filePath, manifest) {
  const hash = await hashFile(filePath);
  const avifPath    = filePath.replace(/\.jpe?g$/i, '.avif');
  const webpPath    = filePath.replace(/\.jpe?g$/i, '.webp');
  const cachedJpeg  = join(CACHE_DIR, `${hash}.jpg`);
  const cachedAvif  = join(CACHE_DIR, `${hash}.avif`);
  const cachedWebp  = join(CACHE_DIR, `${hash}.webp`);

  // キャッシュヒット: キャッシュからコピーしてスキップ
  if (manifest[hash]) {
    await copyFile(cachedJpeg, filePath);
    await copyFile(cachedAvif, avifPath);
    await copyFile(cachedWebp, webpPath);
    console.log(`  [JPEG] ${shortPath(filePath)}: キャッシュ利用（スキップ）`);
    return;
  }

  const before = (await stat(filePath)).size;
  const tmp = filePath + '.opt.tmp';
  try {
    const img = sharp(filePath).resize({ width: MAX_WIDTH, withoutEnlargement: true });

    // AVIF 生成
    await img.clone().avif({ quality: AVIF_QUALITY }).toFile(avifPath);
    // WebP 生成
    await img.clone().webp({ quality: WEBP_QUALITY }).toFile(webpPath);
    // 元 JPEG を圧縮（フォールバック）
    await img.clone().jpeg({ quality: JPEG_QUALITY, mozjpeg: true }).toFile(tmp);

    await unlink(filePath);
    await rename(tmp, filePath);

    // キャッシュに保存
    await mkdir(CACHE_DIR, { recursive: true });
    await copyFile(filePath, cachedJpeg);
    await copyFile(avifPath, cachedAvif);
    await copyFile(webpPath, cachedWebp);
    manifest[hash] = { type: 'jpeg' };

    const after = (await stat(filePath)).size;
    const saved = Math.round((1 - after / before) * 100);
    console.log(`  [JPEG] ${shortPath(filePath)}: ${fmt(before)} → ${fmt(after)} (-${saved}%) + .avif/.webp 生成`);
  } catch (e) {
    try { await unlink(tmp); } catch {}
    console.error(`  [JPEG] エラー: ${shortPath(filePath)}: ${e.message}`);
  }
}

/**
 * PNG を圧縮 + AVIF/WebP 版を生成（キャッシュ対応）
 * 最適化後に元より大きくなった場合は PNG 圧縮をスキップ
 * @param {string} filePath
 * @param {object} manifest
 */
async function processPng(filePath, manifest) {
  const hash = await hashFile(filePath);
  const avifPath   = filePath.replace(/\.png$/i, '.avif');
  const webpPath   = filePath.replace(/\.png$/i, '.webp');
  const cachedPng  = join(CACHE_DIR, `${hash}.png`);
  const cachedAvif = join(CACHE_DIR, `${hash}.avif`);
  const cachedWebp = join(CACHE_DIR, `${hash}.webp`);

  // キャッシュヒット: キャッシュからコピーしてスキップ
  if (manifest[hash]) {
    const entry = manifest[hash];
    // PNG 圧縮済みの場合のみ PNG をキャッシュからコピー（スキップ時は元ファイルのまま）
    if (entry.compressed) {
      await copyFile(cachedPng, filePath);
    }
    await copyFile(cachedAvif, avifPath);
    await copyFile(cachedWebp, webpPath);
    console.log(`  [PNG]  ${shortPath(filePath)}: キャッシュ利用（スキップ）`);
    return;
  }

  const before = (await stat(filePath)).size;
  const tmp = filePath + '.opt.tmp';
  try {
    const img = sharp(filePath).resize({ width: MAX_WIDTH, withoutEnlargement: true });

    // AVIF 生成
    await img.clone().avif({ quality: AVIF_QUALITY }).toFile(avifPath);
    // WebP 生成
    await img.clone().webp({ quality: WEBP_QUALITY, lossless: false }).toFile(webpPath);
    // 元 PNG を圧縮（フォールバック）
    await img.clone().png({ compressionLevel: 9, effort: 10 }).toFile(tmp);

    const after = (await stat(tmp)).size;
    await mkdir(CACHE_DIR, { recursive: true });
    await copyFile(avifPath, cachedAvif);
    await copyFile(webpPath, cachedWebp);

    if (after >= before) {
      // 圧縮後が大きい場合はスキップ（元ファイルをキャッシュに保存）
      await unlink(tmp);
      await copyFile(filePath, cachedPng);
      manifest[hash] = { type: 'png', compressed: false };
      console.log(`  [PNG]  ${shortPath(filePath)}: スキップ（${fmt(after)} > ${fmt(before)}）+ .avif/.webp 生成`);
    } else {
      await unlink(filePath);
      await rename(tmp, filePath);
      await copyFile(filePath, cachedPng);
      manifest[hash] = { type: 'png', compressed: true };
      const saved = Math.round((1 - after / before) * 100);
      console.log(`  [PNG]  ${shortPath(filePath)}: ${fmt(before)} → ${fmt(after)} (-${saved}%) + .avif/.webp 生成`);
    }
  } catch (e) {
    try { await unlink(tmp); } catch {}
    console.error(`  [PNG]  エラー: ${shortPath(filePath)}: ${e.message}`);
  }
}

/**
 * ディレクトリ内の画像を再帰的に処理
 * @param {string} dirPath
 * @param {object} manifest
 */
async function processImagesDir(dirPath, manifest) {
  let entries;
  try {
    entries = await readdir(dirPath, { withFileTypes: true });
  } catch {
    return;
  }
  for (const entry of entries) {
    const fullPath = join(dirPath, entry.name);
    if (entry.isDirectory()) {
      await processImagesDir(fullPath, manifest);
    } else {
      const ext = extname(entry.name).toLowerCase();
      if (['.jpg', '.jpeg'].includes(ext)) await processJpeg(fullPath, manifest);
      else if (ext === '.png')              await processPng(fullPath, manifest);
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
  const distPath  = fileURLToPath(distDir);
  const imagesDir = join(distPath, 'assets', 'images');

  // キャッシュマニフェストを読み込む
  const manifest = await readManifest();

  console.log('\n[1/2] 画像を最適化（AVIF・WebP 生成）...');
  await processImagesDir(imagesDir, manifest);

  // キャッシュマニフェストを保存（新たに処理した分を反映）
  await writeManifest(manifest);

  console.log('\n[2/2] HTML の <img> を <picture> タグに変換...');
  await processHtmlDir(distPath);

  console.log('\n画像最適化が完了しました。\n');
}
