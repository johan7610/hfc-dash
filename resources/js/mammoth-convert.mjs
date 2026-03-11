import mammoth from 'mammoth';
import fs from 'fs';

const inputPath = process.argv[2];
const outputPath = process.argv[3];

if (!inputPath || !outputPath) {
    process.stderr.write(
        JSON.stringify({
            error: 'Usage: node mammoth-convert.mjs ' +
                   '<input.docx> <output.json>'
        })
    );
    process.exit(1);
}

if (!fs.existsSync(inputPath)) {
    process.stderr.write(
        JSON.stringify({
            error: 'File not found: ' + inputPath
        })
    );
    process.exit(1);
}

try {
    const result = await mammoth.convertToHtml(
        { path: inputPath },
        {
            styleMap: [
                "p[style-name='Heading 1'] => h1:fresh",
                "p[style-name='Heading 2'] => h2:fresh",
                "p[style-name='Heading 3'] => h3:fresh",
                "p[style-name='Heading 4'] => h4:fresh",
                "p[style-name='Title'] => h1:fresh",
                "p[style-name='Normal'] => p:fresh",
                "p[style-name='List Paragraph'] " +
                    "=> p.list-paragraph:fresh",
                "r[style-name='Strong'] => strong",
                "r[style-name='Emphasis'] => em",
            ],
            convertImage: mammoth.images.imgElement(
                function(image) {
                    return image.read('base64').then(
                        function(data) {
                            return {
                                src: 'data:' +
                                     image.contentType +
                                     ';base64,' + data
                            };
                        }
                    );
                }
            ),
            includeDefaultStyleMap: true,
            ignoreEmptyParagraphs: true,
        }
    );

    const output = {
        html: result.value,
        messages: result.messages
            .filter(m => m.type === 'warning')
            .map(m => m.message),
    };

    fs.writeFileSync(outputPath, JSON.stringify(output));
    process.exit(0);

} catch (err) {
    process.stderr.write(
        JSON.stringify({ error: err.message })
    );
    process.exit(1);
}
