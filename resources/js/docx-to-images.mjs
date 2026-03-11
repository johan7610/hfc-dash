import mammoth from 'mammoth';
import puppeteer from 'puppeteer';
import fs from 'fs';
import path from 'path';

const inputPath = process.argv[2];
const outputDir = process.argv[3];

if (!inputPath || !outputDir) {
    console.log(JSON.stringify({ error: 'Usage: node docx-to-images.mjs <input.docx> <outputDir>' }));
    process.exit(1);
}

// Convert docx to HTML via Mammoth
const result = await mammoth.convertToHtml(
    { path: inputPath },
    {
        styleMap: [
            "p[style-name='Heading 1'] => h1:fresh",
            "p[style-name='Heading 2'] => h2:fresh",
            "p[style-name='Heading 3'] => h3:fresh",
            "p[style-name='Heading 4'] => h4:fresh",
            "p[style-name='Title'] => h1.doc-title:fresh",
            "p[style-name='Subtitle'] => h2.doc-subtitle:fresh",
            "r[style-name='Strong'] => strong",
            "r[style-name='Emphasis'] => em",
            "p[style-name='List Paragraph'] => p.list-paragraph:fresh",
            "p[style-name='Normal'] => p:fresh",
        ],
        convertImage: mammoth.images.imgElement(
            function(image) {
                return image.read("base64").then(
                    function(imageBuffer) {
                        return {
                            src: "data:" +
                                 image.contentType +
                                 ";base64," +
                                 imageBuffer
                        };
                    }
                );
            }
        ),
        includeDefaultStyleMap: true,
        ignoreEmptyParagraphs: true,
    }
);
const html = result.value;

// Wrap in A4 page HTML
const fullHtml = `<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  body {
    font-family: Arial, sans-serif;
    font-size: 10pt;
    margin: 0;
    padding: 0;
    width: 794px;
  }
  .page {
    width: 794px;
    min-height: 1123px;
    padding: 60px;
    box-sizing: border-box;
    page-break-after: always;
  }
  img { max-width: 100%; }
  table { width: 100%; border-collapse: collapse; }
  td, th { padding: 4px; }
</style>
</head>
<body>
<div class="page">${html}</div>
</body>
</html>`;

// Launch Puppeteer
const browser = await puppeteer.launch({
    args: ['--no-sandbox', '--disable-setuid-sandbox']
});
const page = await browser.newPage();
await page.setViewport({ width: 794, height: 1123 });
await page.setContent(fullHtml, {
    waitUntil: 'networkidle0'
});

// Get page count by measuring content height
const bodyHeight = await page.evaluate(
    () => document.body.scrollHeight
);
const pageCount = Math.ceil(bodyHeight / 1123);

// Screenshot each page
const imagePaths = [];
for (let i = 0; i < pageCount; i++) {
    const imgPath = path.join(
        outputDir, `page-${i + 1}.png`
    );
    await page.screenshot({
        path: imgPath,
        clip: {
            x: 0,
            y: i * 1123,
            width: 794,
            height: 1123
        }
    });
    imagePaths.push(imgPath);
}

await browser.close();

// Output result
console.log(JSON.stringify({
    pages: imagePaths,
    pageCount: pageCount,
    html: html
}));
