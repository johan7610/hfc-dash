<?php

namespace App\Services\Docuperfect;

use App\Models\Docuperfect\SignatureTemplate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SignaturePdfService
{
    /**
     * Generate both internal (with audit trail) and client (clean) signed PDFs.
     *
     * Returns ['internal' => storagePath, 'client' => storagePath] or null on failure.
     */
    public function generate(SignatureTemplate $template): ?array
    {
        try {
            $template->loadMissing(['document.template', 'requests', 'signatures', 'creator']);

            $document = $template->document;
            $docTemplate = $document->template ?? null;

            if (!$docTemplate || $docTemplate->page_count < 1) {
                Log::error('SignaturePdfService: No template or zero pages', [
                    'template_id' => $template->id,
                    'document_id' => $document->id,
                ]);
                return null;
            }

            // Build page images from flattened pages (signatures baked in) or originals
            $flattenedPages = $template->flattened_pages_json ?? [];
            $pages = $this->buildPageImages($docTemplate, $flattenedPages);

            if (empty($pages)) {
                Log::error('SignaturePdfService: No page images found', [
                    'template_id' => $template->id,
                ]);
                return null;
            }

            $basePath = "docuperfect/signed-pdfs/{$template->id}";
            Storage::disk('local')->makeDirectory($basePath);

            // Client copy — clean, no audit watermarks
            $clientPath = "{$basePath}/signed-client.pdf";
            $this->renderPdf($pages, $document->name, $clientPath);

            // Internal copy — with audit trail page appended
            $auditPages = array_merge($pages, [$this->buildAuditPage($template)]);
            $internalPath = "{$basePath}/signed-internal.pdf";
            $this->renderPdf($auditPages, $document->name, $internalPath, true);

            return [
                'internal' => $internalPath,
                'client' => $clientPath,
            ];
        } catch (\Throwable $e) {
            Log::error('SignaturePdfService: PDF generation failed', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Build base64-encoded page images from flattened pages or template originals.
     */
    private function buildPageImages($docTemplate, array $flattenedPages): array
    {
        $pages = [];

        for ($i = 0; $i < $docTemplate->page_count; $i++) {
            // Prefer flattened page (has signatures baked in)
            if (isset($flattenedPages[$i]) && Storage::disk('local')->exists($flattenedPages[$i])) {
                $content = Storage::disk('local')->get($flattenedPages[$i]);
                $mime = $this->detectMime($flattenedPages[$i]);
                $pages[] = "data:{$mime};base64," . base64_encode($content);
                continue;
            }

            // Fall back to original template page
            $pngPath = "docuperfect/templates/{$docTemplate->id}/page-{$i}.png";
            $jpgPath = "docuperfect/templates/{$docTemplate->id}/page-{$i}.jpg";

            if (Storage::disk('local')->exists($pngPath)) {
                $content = Storage::disk('local')->get($pngPath);
                $pages[] = 'data:image/png;base64,' . base64_encode($content);
            } elseif (Storage::disk('local')->exists($jpgPath)) {
                $content = Storage::disk('local')->get($jpgPath);
                $pages[] = 'data:image/jpeg;base64,' . base64_encode($content);
            }
        }

        return $pages;
    }

    /**
     * Render a PDF from page images and save to storage.
     */
    private function renderPdf(array $pages, string $documentName, string $storagePath, bool $isInternal = false): void
    {
        $html = view('docuperfect.signatures.pdf.signed-document', [
            'pages' => $pages,
            'documentName' => $documentName,
            'isInternal' => $isInternal,
        ])->render();

        $pdf = Pdf::loadHTML($html);
        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->setOption('isHtml5ParserEnabled', true);

        $fullPath = Storage::disk('local')->path($storagePath);
        file_put_contents($fullPath, $pdf->output());
    }

    /**
     * Build an HTML audit trail page as a data URI image (rendered as HTML in the PDF).
     * Returns a special marker that the view template handles differently.
     */
    private function buildAuditPage(SignatureTemplate $template): string
    {
        $progress = $template->partyProgress();
        $document = $template->document;

        $html = '<div style="font-family:Arial,sans-serif;padding:40px;font-size:11px;">';
        $html .= '<h2 style="color:#0b2a4a;border-bottom:2px solid #0b2a4a;padding-bottom:8px;">Certificate of Authenticity</h2>';
        $html .= '<p><strong>Document:</strong> ' . e($document->name) . '</p>';
        $html .= '<p><strong>Completed:</strong> ' . ($template->completed_at?->format('d M Y H:i') ?? 'N/A') . '</p>';
        $html .= '<p><strong>Document Hash:</strong> ' . ($template->document_hash ?? 'N/A') . '</p>';
        $html .= '<hr style="margin:15px 0;">';
        $html .= '<h3 style="color:#0b2a4a;">Signing Parties</h3>';
        $html .= '<table style="width:100%;border-collapse:collapse;font-size:11px;">';
        $html .= '<tr style="background:#f1f5f9;"><th style="padding:6px;text-align:left;border:1px solid #e0e0e0;">Party</th><th style="padding:6px;text-align:left;border:1px solid #e0e0e0;">Name</th><th style="padding:6px;text-align:left;border:1px solid #e0e0e0;">Method</th><th style="padding:6px;text-align:left;border:1px solid #e0e0e0;">Completed</th></tr>';

        foreach ($progress as $role => $party) {
            $method = ucfirst(str_replace('_', ' ', $party['signing_method'] ?? 'electronic'));
            $completedAt = $party['completed_at'] ? $party['completed_at']->format('d M Y H:i') : 'Pending';
            $html .= '<tr>';
            $html .= '<td style="padding:6px;border:1px solid #e0e0e0;">' . e(ucfirst(str_replace('_', ' ', $role))) . '</td>';
            $html .= '<td style="padding:6px;border:1px solid #e0e0e0;">' . e($party['name']) . '</td>';
            $html .= '<td style="padding:6px;border:1px solid #e0e0e0;">' . e($method) . '</td>';
            $html .= '<td style="padding:6px;border:1px solid #e0e0e0;">' . e($completedAt) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';
        $html .= '<p style="margin-top:20px;color:#666;font-size:10px;">This document was signed in accordance with the Electronic Communications and Transactions Act 25 of 2002.</p>';
        $html .= '</div>';

        // Return as a special audit page marker
        return 'audit:' . base64_encode($html);
    }

    /**
     * Detect MIME type from file path.
     */
    private function detectMime(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => 'image/png',
        };
    }
}
