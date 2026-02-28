<?php

namespace App\Services\Docuperfect;

use App\Models\Docuperfect\Signature;
use App\Models\Docuperfect\SignatureMarker;
use App\Models\Docuperfect\SignatureTemplate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DocumentFlattener
{
    /**
     * Flatten all document field values onto the original page images.
     * Call this when agent completes field editing and proceeds to signatures.
     *
     * Reads fields from Document->fields_json, converts percentage positions to pixels,
     * and renders text/strikethroughs directly onto page image files using GD.
     *
     * @return array<int, string> Map of page number (0-indexed) => storage path
     */
    public function flattenFields(SignatureTemplate $template): array
    {
        $document = $template->document;
        $docTemplate = $document->template;

        if (!$docTemplate || $docTemplate->page_count < 1) {
            Log::warning('DocumentFlattener::flattenFields — no template or zero pages', ['template_id' => $template->id]);
            return [];
        }

        $fields = $document->fields_json ?? [];
        $newPaths = [];

        for ($pageNum = 0; $pageNum < $docTemplate->page_count; $pageNum++) {
            $imagePath = $this->findOriginalPageImage($docTemplate->id, $pageNum);
            if (!$imagePath) {
                Log::warning("DocumentFlattener: page image not found", ['docTemplate' => $docTemplate->id, 'page' => $pageNum]);
                continue;
            }

            $fullPath = Storage::disk('local')->path($imagePath);
            $image = $this->loadImage($fullPath);
            if (!$image) continue;

            $imgWidth = imagesx($image);
            $imgHeight = imagesy($image);

            // Filter fields for this page (pageIndex is 0-indexed)
            $pageFields = array_filter($fields, fn($f) => ($f['pageIndex'] ?? 0) == $pageNum);

            foreach ($pageFields as $field) {
                $type = $field['type'] ?? 'placeholder';

                // Skip signature/initial fields — those are handled by signature markers
                if (in_array($type, ['signature', 'initial'])) {
                    continue;
                }

                $pos = $field['position'] ?? [];
                $size = $field['size'] ?? [];
                $style = $field['style'] ?? [];

                $x = (floatval($pos['x'] ?? 0) / 100) * $imgWidth;
                $y = (floatval($pos['y'] ?? 0) / 100) * $imgHeight;
                $w = (floatval($size['width'] ?? 10) / 100) * $imgWidth;
                $h = (floatval($size['height'] ?? 3) / 100) * $imgHeight;

                switch ($type) {
                    case 'placeholder':
                        $value = trim((string) ($field['value'] ?? ''));
                        if ($value !== '') {
                            $this->renderText($image, $value, $x, $y, $w, $h, $style);
                        }
                        break;

                    case 'date':
                        $value = trim((string) ($field['value'] ?? ''));
                        if ($value !== '') {
                            $this->renderText($image, $value, $x, $y, $w, $h, $style);
                        }
                        break;

                    case 'selection':
                        $value = trim((string) ($field['selectedValue'] ?? ''));
                        if ($value !== '') {
                            $this->renderText($image, $value, $x, $y, $w, $h, $style);
                        }
                        break;

                    case 'condition':
                        $value = trim((string) ($field['text'] ?? ''));
                        if ($value !== '') {
                            // Conditions get a white background fill first
                            $white = imagecolorallocatealpha($image, 255, 255, 255, 30); // ~88% opaque
                            imagefilledrectangle($image, (int) $x, (int) $y, (int) ($x + $w), (int) ($y + $h), $white);
                            $this->renderText($image, $value, $x, $y, $w, $h, $style);
                        }
                        break;

                    case 'strikethrough':
                        if (!empty($field['active'])) {
                            $this->renderStrikethrough($image, $x, $y, $w, $h, $field);
                        }
                        break;
                }
            }

            // Save flattened page
            $newPath = $this->saveFlattenedPage($template->id, $pageNum, $image);
            $newPaths[$pageNum] = $newPath;

            imagedestroy($image);
        }

        // Store the flattened page paths on the template
        $template->update([
            'flattened_pages_json' => $newPaths,
        ]);

        return $newPaths;
    }

    /**
     * Flatten a signature onto the current (possibly already flattened) page image.
     * Call this after each signature is captured.
     *
     * @return string New page image path
     */
    public function flattenSignature(SignatureTemplate $template, SignatureMarker $marker, Signature $signature): string
    {
        $pageNum = $marker->page_number - 1; // markers are 1-indexed, images are 0-indexed
        $currentPages = $template->flattened_pages_json ?? [];

        // Use flattened page if available, otherwise original
        $imagePath = $currentPages[$pageNum] ?? $this->findOriginalPageImage(
            $template->document->template->id ?? 0, $pageNum
        );

        if (!$imagePath) {
            Log::warning("DocumentFlattener::flattenSignature — image not found", [
                'template_id' => $template->id,
                'page' => $pageNum,
            ]);
            return '';
        }

        $fullPath = Storage::disk('local')->path($imagePath);
        $image = $this->loadImage($fullPath);
        if (!$image) return $imagePath;

        $imgWidth = imagesx($image);
        $imgHeight = imagesy($image);

        // Convert marker percentage positions to pixels
        $x = (floatval($marker->x_position) / 100) * $imgWidth;
        $y = (floatval($marker->y_position) / 100) * $imgHeight;
        $w = (floatval($marker->width) / 100) * $imgWidth;
        $h = (floatval($marker->height) / 100) * $imgHeight;

        // Composite the client-rendered image onto the page.
        // The client renders drawn signatures, typed signatures, text inputs, and dates
        // as canvas images — always use that image when available so text markers
        // render as normal text (not signature-style).
        if ($signature->signature_data) {
            $this->compositeSignatureImage($image, $signature->signature_data, $x, $y, $w, $h);
        } elseif ($signature->signature_type === 'typed') {
            // Fallback: render typed name when no image data available
            $this->renderTypedSignature($image, $signature->signer_name, $x, $y, $w, $h);
        }

        // Audit metadata (signer name, timestamp) is stored in the database
        // and appears on the audit certificate — not rendered on the document page.

        // Save the updated page
        $newPath = $this->saveFlattenedPage($template->id, $pageNum, $image);

        // Update the flattened pages record
        $currentPages[$pageNum] = $newPath;
        $template->update(['flattened_pages_json' => $currentPages]);

        imagedestroy($image);

        return $newPath;
    }

    /**
     * Flatten a wet-ink-verified stamp onto the page image.
     */
    public function flattenWetInkStamp(SignatureTemplate $template, SignatureMarker $marker, string $signerName): string
    {
        $pageNum = $marker->page_number - 1;
        $currentPages = $template->flattened_pages_json ?? [];

        $imagePath = $currentPages[$pageNum] ?? $this->findOriginalPageImage(
            $template->document->template->id ?? 0, $pageNum
        );

        if (!$imagePath) return '';

        $fullPath = Storage::disk('local')->path($imagePath);
        $image = $this->loadImage($fullPath);
        if (!$image) return $imagePath;

        $imgWidth = imagesx($image);
        $imgHeight = imagesy($image);

        $x = (floatval($marker->x_position) / 100) * $imgWidth;
        $y = (floatval($marker->y_position) / 100) * $imgHeight;
        $w = (floatval($marker->width) / 100) * $imgWidth;
        $h = (floatval($marker->height) / 100) * $imgHeight;

        // Draw a green box with "WET INK VERIFIED" text
        $green = imagecolorallocate($image, 39, 103, 73);
        $lightGreen = imagecolorallocatealpha($image, 240, 255, 244, 50);
        imagefilledrectangle($image, (int) $x, (int) $y, (int) ($x + $w), (int) ($y + $h), $lightGreen);
        imagerectangle($image, (int) $x, (int) $y, (int) ($x + $w), (int) ($y + $h), $green);

        // Center text
        $font = $this->findFont(true);
        if ($font && function_exists('imagettftext')) {
            $fontSize = max(8, $h * 0.3);
            $fontSize = min($fontSize, 14);
            $textY1 = $y + ($h * 0.4);
            $textY2 = $y + ($h * 0.7);
            imagettftext($image, $fontSize, 0, (int) $x + 4, (int) $textY1, $green, $font, 'WET INK');
            imagettftext($image, $fontSize, 0, (int) $x + 4, (int) $textY2, $green, $font, 'VERIFIED');
        } else {
            imagestring($image, 2, (int) $x + 4, (int) ($y + $h * 0.3), 'WET INK', $green);
            imagestring($image, 2, (int) $x + 4, (int) ($y + $h * 0.6), 'VERIFIED', $green);
        }

        $newPath = $this->saveFlattenedPage($template->id, $pageNum, $image);
        $currentPages[$pageNum] = $newPath;
        $template->update(['flattened_pages_json' => $currentPages]);

        imagedestroy($image);
        return $newPath;
    }

    /**
     * Get flattened page image paths for a template, falling back to originals.
     *
     * @return array<int, string> Map of 0-indexed page number => storage path
     */
    public function getPageImages(SignatureTemplate $template): array
    {
        $flattenedPages = $template->flattened_pages_json ?? [];
        $docTemplate = $template->document->template ?? null;

        if (!$docTemplate || $docTemplate->page_count < 1) {
            return $flattenedPages;
        }

        $pages = [];
        for ($pageNum = 0; $pageNum < $docTemplate->page_count; $pageNum++) {
            $pages[$pageNum] = $flattenedPages[$pageNum]
                ?? $this->findOriginalPageImage($docTemplate->id, $pageNum);
        }

        return $pages;
    }

    /**
     * Create temporary annotated copies of page images with marker overlays.
     * Used for wet-ink download PDFs so signers see where to sign/initial/fill.
     *
     * @param  \Illuminate\Support\Collection  $markers  Markers for the party
     * @return array<int, string> Map of 0-indexed page => absolute temp file path
     */
    public function createAnnotatedPages(SignatureTemplate $template, $markers): array
    {
        $pageImages = $this->getPageImages($template);
        if (empty($pageImages)) return [];

        $tempPaths = [];

        foreach ($pageImages as $pageNum => $storagePath) {
            if (!$storagePath || !Storage::disk('local')->exists($storagePath)) {
                continue;
            }

            $fullPath = Storage::disk('local')->path($storagePath);
            $image = $this->loadImage($fullPath);
            if (!$image) continue;

            // Filter markers for this page (markers are 1-indexed, pageNum is 0-indexed)
            $pageMarkers = $markers->filter(fn($m) => ($m->page_number - 1) === $pageNum);

            if ($pageMarkers->isNotEmpty()) {
                $this->overlayMarkerAnnotations($image, $pageMarkers);
            }

            // Save to temp file
            $tempPath = sys_get_temp_dir() . '/dp_annotated_' . $template->id . '_page_' . $pageNum . '_' . uniqid() . '.png';
            imagepng($image, $tempPath, 6);
            imagedestroy($image);

            $tempPaths[$pageNum] = $tempPath;
        }

        return $tempPaths;
    }

    /**
     * Delete temporary annotated page images after PDF generation.
     */
    public static function cleanupTempImages(array $tempPaths): void
    {
        foreach ($tempPaths as $path) {
            if (file_exists($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * Overlay colored marker annotations onto a GD image.
     */
    private function overlayMarkerAnnotations($image, $markers): void
    {
        $imgWidth = imagesx($image);
        $imgHeight = imagesy($image);

        // GD alpha: 0 = opaque, 127 = transparent. ~30% opacity → alpha 89
        $alpha = 89;

        $configs = [
            SignatureMarker::TYPE_SIGNATURE => [
                'fill' => [173, 216, 230, $alpha], 'border' => [0, 102, 204], 'label' => 'SIGN HERE',
            ],
            SignatureMarker::TYPE_INITIAL => [
                'fill' => [144, 238, 144, $alpha], 'border' => [34, 139, 34], 'label' => 'INITIAL HERE',
            ],
            SignatureMarker::TYPE_TEXT => [
                'fill' => [255, 255, 200, $alpha], 'border' => [180, 160, 0], 'label' => 'FILL IN',
            ],
            SignatureMarker::TYPE_DATE => [
                'fill' => [255, 200, 130, $alpha], 'border' => [210, 130, 0], 'label' => 'DATE',
            ],
        ];

        imagealphablending($image, true);

        foreach ($markers as $marker) {
            $type = $marker->type ?? SignatureMarker::TYPE_SIGNATURE;
            $cfg = $configs[$type] ?? $configs[SignatureMarker::TYPE_SIGNATURE];

            $x = (floatval($marker->x_position) / 100) * $imgWidth;
            $y = (floatval($marker->y_position) / 100) * $imgHeight;
            $w = (floatval($marker->width) / 100) * $imgWidth;
            $h = (floatval($marker->height) / 100) * $imgHeight;

            // Semi-transparent fill
            $fillColor = imagecolorallocatealpha($image, $cfg['fill'][0], $cfg['fill'][1], $cfg['fill'][2], $cfg['fill'][3]);
            imagefilledrectangle($image, (int) $x, (int) $y, (int) ($x + $w), (int) ($y + $h), $fillColor);

            // 2px solid border
            $borderColor = imagecolorallocate($image, $cfg['border'][0], $cfg['border'][1], $cfg['border'][2]);
            imagesetthickness($image, 2);
            imagerectangle($image, (int) $x, (int) $y, (int) ($x + $w), (int) ($y + $h), $borderColor);
            imagesetthickness($image, 1);

            // Centered label text
            $this->renderMarkerLabel($image, $cfg['label'], $x, $y, $w, $h);
        }
    }

    /**
     * Render a centered bold label inside a marker annotation box.
     */
    private function renderMarkerLabel($image, string $label, float $x, float $y, float $w, float $h): void
    {
        $textColor = imagecolorallocate($image, 0, 0, 0);
        $font = $this->findFont(true);

        if ($font && function_exists('imagettftext')) {
            // Target ~60% of box height
            $fontSize = max(8, $h * 0.6);

            // Shrink if text overflows box width
            $bbox = imagettfbbox($fontSize, 0, $font, $label);
            $textWidth = abs($bbox[2] - $bbox[0]);
            while ($textWidth > $w * 0.9 && $fontSize > 6) {
                $fontSize -= 1;
                $bbox = imagettfbbox($fontSize, 0, $font, $label);
                $textWidth = abs($bbox[2] - $bbox[0]);
            }

            // Center horizontally and vertically
            $textX = $x + ($w - $textWidth) / 2;
            $textHeight = abs($bbox[7] - $bbox[1]);
            $textY = $y + ($h + $textHeight) / 2;

            imagettftext($image, $fontSize, 0, (int) $textX, (int) $textY, $textColor, $font, $label);
        } else {
            // Fallback: GD built-in font
            $charWidth = imagefontwidth(3);
            $builtinTextW = strlen($label) * $charWidth;
            $textX = $x + ($w - $builtinTextW) / 2;
            $textY = $y + ($h - imagefontheight(3)) / 2;
            imagestring($image, 3, (int) $textX, (int) $textY, $label, $textColor);
        }
    }

    // ========== HELPER METHODS ==========

    /**
     * Find the original page image storage path for a document template page.
     */
    private function findOriginalPageImage(int $templateId, int $pageNum): ?string
    {
        $pngPath = "docuperfect/templates/{$templateId}/page-{$pageNum}.png";
        $jpgPath = "docuperfect/templates/{$templateId}/page-{$pageNum}.jpg";

        if (Storage::disk('local')->exists($pngPath)) {
            return $pngPath;
        }
        if (Storage::disk('local')->exists($jpgPath)) {
            return $jpgPath;
        }

        return null;
    }

    /**
     * Load an image file (supports JPEG, PNG, GIF, WebP).
     *
     * @return \GdImage|false|null
     */
    private function loadImage(string $path)
    {
        if (!file_exists($path)) return null;

        $info = @getimagesize($path);
        if (!$info) return null;

        return match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_GIF => @imagecreatefromgif($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            default => null,
        };
    }

    /**
     * Render text onto an image at the specified pixel position.
     */
    private function renderText($image, string $text, float $x, float $y, float $w, float $h, array $style = []): void
    {
        $color = imagecolorallocate($image, 0, 0, 0); // Black text

        // If solidBackground is set, fill the area white first
        if (!empty($style['solidBackground'])) {
            $white = imagecolorallocate($image, 255, 255, 255);
            imagefilledrectangle($image, (int) $x, (int) $y, (int) ($x + $w), (int) ($y + $h), $white);
        }

        $bold = !empty($style['bold']);
        $ttfFont = $this->findFont($bold);

        if ($ttfFont && function_exists('imagettftext')) {
            // Scale font size to match ~11pt physical text on an A4 page.
            // GD's imagettftext renders at 96 DPI internally.
            // Compute the image's effective DPI from its width vs A4 (8.27 inches),
            // then scale the target point size by (effectiveDPI / 96).
            $imgWidth = imagesx($image);
            $targetPt = floatval($style['fontSize'] ?? 11);
            $targetPt = max(8, min(16, $targetPt));
            $effectiveDpi = $imgWidth / 8.27; // A4 width in inches
            $fontSize = $targetPt * ($effectiveDpi / 96);

            // Cap so text never exceeds field height
            $fontSize = min($fontSize, $h * 0.75);

            // Baseline near bottom of field box — text sits ON the line.
            // Leave ~30% of font size below baseline for descenders.
            $textY = $y + $h - ($fontSize * 0.3);

            imagettftext($image, $fontSize, 0, (int) ($x + 2), (int) $textY, $color, $ttfFont, $text);
        } else {
            // Fallback to GD built-in fonts
            $font = $h > 20 ? 3 : 2;
            imagestring($image, $font, (int) $x + 2, (int) $y + 2, $text, $color);
        }
    }

    /**
     * Render a strikethrough line across the field area.
     */
    private function renderStrikethrough($image, float $x, float $y, float $w, float $h, array $field = []): void
    {
        $red = imagecolorallocate($image, 239, 68, 68);
        imagesetthickness($image, 2);

        $strikeType = $field['strikethroughType'] ?? 'horizontal';

        if ($strikeType === 'diagonal') {
            imageline($image, (int) $x, (int) $y, (int) ($x + $w), (int) ($y + $h), $red);
        } else {
            // Horizontal line through the middle
            imageline($image, (int) $x, (int) ($y + $h / 2), (int) ($x + $w), (int) ($y + $h / 2), $red);
        }

        imagesetthickness($image, 1); // Reset
    }

    /**
     * Composite a base64 signature image onto the page image.
     */
    private function compositeSignatureImage($image, string $signatureDataUri, float $x, float $y, float $w, float $h): void
    {
        // Decode the signature image from base64 data URI
        $sigData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $signatureDataUri));
        if (!$sigData) return;

        $sigImage = @imagecreatefromstring($sigData);
        if (!$sigImage) return;

        // Create a properly sized version of the signature
        $destW = (int) $w;
        $destH = (int) $h;
        if ($destW < 1 || $destH < 1) {
            imagedestroy($sigImage);
            return;
        }

        $resized = imagecreatetruecolor($destW, $destH);

        // Preserve transparency
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
        imagefilledrectangle($resized, 0, 0, $destW, $destH, $transparent);
        imagealphablending($resized, true);

        // Resize signature to fit marker area
        imagecopyresampled(
            $resized, $sigImage,
            0, 0, 0, 0,
            $destW, $destH,
            imagesx($sigImage), imagesy($sigImage)
        );

        // Composite onto page (with alpha blending)
        imagealphablending($image, true);
        imagecopy($image, $resized, (int) $x, (int) $y, 0, 0, $destW, $destH);

        imagedestroy($sigImage);
        imagedestroy($resized);
    }

    /**
     * Render a typed signature name in a stylised font.
     */
    private function renderTypedSignature($image, string $name, float $x, float $y, float $w, float $h): void
    {
        $color = imagecolorallocate($image, 26, 26, 138); // Dark blue, matching CSS
        $font = $this->findFont(false);

        if ($font && function_exists('imagettftext')) {
            $fontSize = max(10, $h * 0.6);
            $fontSize = min($fontSize, $h * 0.85);
            $textY = $y + ($h * 0.7);
            // Center horizontally
            $bbox = imagettfbbox($fontSize, 0, $font, $name);
            $textWidth = abs($bbox[2] - $bbox[0]);
            $textX = $x + ($w - $textWidth) / 2;
            $textX = max($textX, $x + 2);

            imagettftext($image, $fontSize, 0, (int) $textX, (int) $textY, $color, $font, $name);
        } else {
            imagestring($image, 4, (int) $x + 4, (int) ($y + $h * 0.3), $name, $color);
        }
    }

    /**
     * Find a TTF font file to use for text rendering.
     */
    private function findFont(bool $bold = false): ?string
    {
        if ($bold) {
            $candidates = [
                'C:/Windows/Fonts/arialbd.ttf',
                resource_path('fonts/arial-bold.ttf'),
                '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
                '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
            ];
        } else {
            $candidates = [
                'C:/Windows/Fonts/arial.ttf',
                resource_path('fonts/arial.ttf'),
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
                '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            ];
        }

        foreach ($candidates as $font) {
            if (file_exists($font)) return $font;
        }

        return null;
    }

    /**
     * Save a flattened page image as PNG.
     */
    private function saveFlattenedPage(int $templateId, int $pageNum, $image): string
    {
        $dir = "docuperfect/signed-documents/{$templateId}/flattened";
        $path = "{$dir}/page_{$pageNum}.png";

        $fullPath = Storage::disk('local')->path($path);

        // Ensure directory exists
        $dirPath = dirname($fullPath);
        if (!is_dir($dirPath)) {
            mkdir($dirPath, 0755, true);
        }

        // Save as PNG for lossless quality
        imagepng($image, $fullPath, 6);

        return $path;
    }
}
