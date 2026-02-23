/**
 * Convert a self-contained HTML file to PDF using Edge (Chromium) via puppeteer-core.
 *
 * Usage: node scripts/html-to-pdf.mjs <input.html> <output.pdf>
 *
 * Uses the system-installed Microsoft Edge browser — no additional browser download required.
 * Produces output identical to Ctrl+P → Save as PDF in the browser.
 */
import puppeteer from 'puppeteer-core';
import { existsSync } from 'fs';
import { resolve } from 'path';
import { pathToFileURL } from 'url';

const [,, inputArg, outputArg] = process.argv;

if (!inputArg || !outputArg) {
    console.error('Usage: node scripts/html-to-pdf.mjs <input.html> <output.pdf>');
    process.exit(1);
}

const inputPath  = resolve(inputArg);
const outputPath = resolve(outputArg);

if (!existsSync(inputPath)) {
    console.error(`Input file not found: ${inputPath}`);
    process.exit(1);
}

// Locate Edge — standard install paths on Windows
const edgePaths = [
    'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
    'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
    process.env.EDGE_PATH,
].filter(Boolean);

const executablePath = edgePaths.find(p => existsSync(p));
if (!executablePath) {
    console.error('Microsoft Edge not found. Set EDGE_PATH environment variable.');
    process.exit(1);
}

let browser;
try {
    browser = await puppeteer.launch({
        executablePath,
        headless: true,
        args: ['--no-sandbox', '--disable-gpu', '--disable-software-rasterizer'],
    });

    const page = await browser.newPage();

    // Navigate to the local HTML file
    const fileUrl = pathToFileURL(inputPath).href;
    await page.goto(fileUrl, { waitUntil: 'networkidle0', timeout: 30000 });

    // Emulate print media for @media print styles
    await page.emulateMediaType('print');

    // Generate PDF matching browser Ctrl+P output
    await page.pdf({
        path: outputPath,
        format: 'A4',
        margin: {
            top:    '15mm',
            right:  '18mm',
            bottom: '20mm',
            left:   '18mm',
        },
        printBackground: true,
        preferCSSPageSize: true,
    });

    console.log(JSON.stringify({ success: true, output: outputPath }));
} catch (err) {
    console.error(JSON.stringify({ success: false, error: err.message }));
    process.exit(1);
} finally {
    if (browser) await browser.close();
}
