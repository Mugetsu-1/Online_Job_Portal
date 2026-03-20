import { cpSync, existsSync, mkdirSync, readdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import path from 'node:path';

const rootDir = process.cwd();
const frontendDir = path.join(rootDir, 'frontend');
const assetsDir = path.join(rootDir, 'assets');
const distDir = path.join(rootDir, 'dist');

if (existsSync(distDir)) {
  rmSync(distDir, { recursive: true, force: true });
}

mkdirSync(distDir, { recursive: true });
cpSync(assetsDir, path.join(distDir, 'assets'), { recursive: true });

const apiBase = (process.env.FRONTEND_API_BASE_URL || '').trim().replace(/\/+$/, '');
const runtimeConfig = apiBase
  ? `window.API_BASE_URL = ${JSON.stringify(apiBase)};\n`
  : 'window.API_BASE_URL = window.API_BASE_URL || "";\n';
writeFileSync(path.join(distDir, 'runtime-config.js'), runtimeConfig, 'utf8');

for (const fileName of readdirSync(frontendDir)) {
  if (!fileName.endsWith('.html')) continue;

  const sourcePath = path.join(frontendDir, fileName);
  const targetPath = path.join(distDir, fileName);
  let html = readFileSync(sourcePath, 'utf8');

  html = html.replaceAll('../assets/', 'assets/');
  html = html.replace(
    '<script src="assets/js/api.js"></script>',
    '<script src="runtime-config.js"></script>\n  <script src="assets/js/api.js"></script>'
  );

  writeFileSync(targetPath, html, 'utf8');
}

console.log(`Built frontend to ${distDir}`);
