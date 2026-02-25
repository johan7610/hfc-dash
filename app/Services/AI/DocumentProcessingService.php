<?php

namespace App\Services\AI;

use App\Models\KnowledgeDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentProcessingService
{
    private const CHUNK_SIZE = 2500;
    private const CHUNK_OVERLAP = 300;

    /**
     * Process an uploaded file: store, create record, extract text, chunk, index.
     */
    public function processUpload(
        UploadedFile $file,
        int $categoryId,
        int $uploadedBy,
        string $title,
        ?string $description = null,
        ?string $version = null
    ): KnowledgeDocument {
        $extension = strtolower($file->getClientOriginalExtension());
        $fileName = $file->getClientOriginalName();
        $storedPath = $file->store('knowledge-documents', 'local');

        $document = KnowledgeDocument::create([
            'category_id' => $categoryId,
            'uploaded_by' => $uploadedBy,
            'title' => $title,
            'description' => $description,
            'file_path' => $storedPath,
            'file_name' => $fileName,
            'file_type' => $extension,
            'file_size' => $file->getSize(),
            'status' => 'processing',
            'version' => $version,
        ]);

        try {
            $fullPath = storage_path('app/' . $storedPath);
            $text = $this->extractText($fullPath, $extension);

            if (empty(trim($text))) {
                $document->update([
                    'status' => 'error',
                    'error_message' => 'No text could be extracted from this file.',
                ]);
                return $document;
            }

            $chunks = $this->chunkText($text);
            $this->storeChunks($document, $chunks);

            $document->update([
                'status' => 'ready',
                'chunk_count' => count($chunks),
                'error_message' => null,
            ]);
        } catch (\Throwable $e) {
            \Log::error('Knowledge document processing failed', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);

            $document->update([
                'status' => 'error',
                'error_message' => 'Processing failed: ' . Str::limit($e->getMessage(), 500),
            ]);
        }

        return $document;
    }

    /**
     * Re-process an existing document: delete chunks, re-extract and re-chunk.
     */
    public function reprocess(KnowledgeDocument $document): KnowledgeDocument
    {
        $document->chunks()->delete();
        $document->update(['status' => 'processing', 'error_message' => null]);

        try {
            $fullPath = storage_path('app/' . $document->file_path);
            $text = $this->extractText($fullPath, $document->file_type);

            if (empty(trim($text))) {
                $document->update([
                    'status' => 'error',
                    'error_message' => 'No text could be extracted from this file.',
                ]);
                return $document;
            }

            $chunks = $this->chunkText($text);
            $this->storeChunks($document, $chunks);

            $document->update([
                'status' => 'ready',
                'chunk_count' => count($chunks),
                'error_message' => null,
            ]);
        } catch (\Throwable $e) {
            \Log::error('Knowledge document reprocessing failed', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);

            $document->update([
                'status' => 'error',
                'error_message' => 'Reprocessing failed: ' . Str::limit($e->getMessage(), 500),
            ]);
        }

        return $document;
    }

    /**
     * Extract text from a file based on its type.
     */
    private function extractText(string $path, string $type): string
    {
        return match ($type) {
            'pdf' => $this->extractFromPdf($path),
            'docx', 'doc' => $this->extractFromDocx($path),
            'txt', 'md' => file_get_contents($path) ?: '',
            default => '',
        };
    }

    /**
     * Extract text from a PDF using pdftotext CLI.
     */
    private function extractFromPdf(string $path): string
    {
        $escapedPath = escapeshellarg($path);

        // Try pdftotext with -layout flag first
        $output = [];
        $returnCode = 0;
        exec("pdftotext -layout {$escapedPath} -", $output, $returnCode);

        if ($returnCode === 0 && !empty($output)) {
            return implode("\n", $output);
        }

        // Fallback: pdftotext without -layout
        $output = [];
        exec("pdftotext {$escapedPath} -", $output, $returnCode);

        if ($returnCode === 0 && !empty($output)) {
            return implode("\n", $output);
        }

        // Final fallback: smalot/pdfparser if available
        if (class_exists(\Smalot\PdfParser\Parser::class)) {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($path);
            return $pdf->getText();
        }

        return '';
    }

    /**
     * Extract text from a DOCX using pandoc CLI.
     */
    private function extractFromDocx(string $path): string
    {
        $escapedPath = escapeshellarg($path);

        // Try pandoc first
        $output = [];
        $returnCode = 0;
        exec("pandoc {$escapedPath} -t plain", $output, $returnCode);

        if ($returnCode === 0 && !empty($output)) {
            return implode("\n", $output);
        }

        // Fallback: ZipArchive XML extraction
        return $this->extractDocxViaZip($path);
    }

    /**
     * Extract text from DOCX by reading the XML inside the zip.
     */
    private function extractDocxViaZip(string $path): string
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }

        $content = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($content === false) {
            return '';
        }

        // Strip XML tags and decode entities
        $text = strip_tags($content);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return $text;
    }

    /**
     * Split text into overlapping chunks with section detection.
     */
    private function chunkText(string $text): array
    {
        $text = trim($text);
        if (empty($text)) {
            return [];
        }

        $chunks = [];
        $offset = 0;
        $textLength = mb_strlen($text);

        while ($offset < $textLength) {
            $chunkText = mb_substr($text, $offset, self::CHUNK_SIZE);
            $actualLength = mb_strlen($chunkText);

            // Try to break at a paragraph boundary
            if ($offset + $actualLength < $textLength) {
                $lastParagraph = mb_strrpos($chunkText, "\n\n");
                if ($lastParagraph !== false && $lastParagraph > (self::CHUNK_SIZE * 0.5)) {
                    $chunkText = mb_substr($chunkText, 0, $lastParagraph);
                } else {
                    // Try sentence boundary
                    $lastSentence = max(
                        mb_strrpos($chunkText, '. ') ?: 0,
                        mb_strrpos($chunkText, ".\n") ?: 0,
                        mb_strrpos($chunkText, '? ') ?: 0,
                        mb_strrpos($chunkText, "!\n") ?: 0,
                    );
                    if ($lastSentence > (self::CHUNK_SIZE * 0.5)) {
                        $chunkText = mb_substr($chunkText, 0, $lastSentence + 1);
                    } else {
                        // Try word boundary
                        $lastSpace = mb_strrpos($chunkText, ' ');
                        if ($lastSpace !== false && $lastSpace > (self::CHUNK_SIZE * 0.5)) {
                            $chunkText = mb_substr($chunkText, 0, $lastSpace);
                        }
                    }
                }
            }

            $chunkText = trim($chunkText);
            if (!empty($chunkText)) {
                $sectionTitle = $this->detectSectionTitle($chunkText);
                $wordCount = str_word_count($chunkText);

                $chunks[] = [
                    'content' => $chunkText,
                    'section_title' => $sectionTitle,
                    'char_count' => mb_strlen($chunkText),
                    'word_count' => $wordCount,
                ];
            }

            $advance = mb_strlen($chunkText);
            $offset += max($advance - self::CHUNK_OVERLAP, 1);
        }

        return $chunks;
    }

    /**
     * Detect section title from the first line of a chunk.
     */
    private function detectSectionTitle(string $chunk): ?string
    {
        $firstLine = trim(strtok($chunk, "\n"));
        if (empty($firstLine) || mb_strlen($firstLine) > 120) {
            return null;
        }

        // All uppercase line
        if (mb_strtoupper($firstLine) === $firstLine && mb_strlen($firstLine) > 3) {
            return $firstLine;
        }

        // Numbered section (e.g., "1. Introduction", "1.2 Scope")
        if (preg_match('/^\d+(\.\d+)*[\.\)]\s+\S/', $firstLine)) {
            return $firstLine;
        }

        // Starts with CHAPTER, SECTION, CLAUSE, PART, SCHEDULE, ANNEXURE
        if (preg_match('/^(CHAPTER|SECTION|CLAUSE|PART|SCHEDULE|ANNEXURE|APPENDIX)\s/i', $firstLine)) {
            return $firstLine;
        }

        return null;
    }

    /**
     * Store chunks for a document.
     */
    private function storeChunks(KnowledgeDocument $document, array $chunks): void
    {
        foreach ($chunks as $index => $chunk) {
            $document->chunks()->create([
                'chunk_index' => $index,
                'content' => $chunk['content'],
                'section_title' => $chunk['section_title'],
                'char_count' => $chunk['char_count'],
                'word_count' => $chunk['word_count'],
            ]);
        }
    }
}
