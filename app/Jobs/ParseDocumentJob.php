<?php

namespace App\Jobs;

use App\Services\Docuperfect\DocxParserService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ParseDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutes
    public int $tries = 1;

    public function __construct(
        public string $filePath,
        public string $templateName,
        public string $originalFilename,
        public int $userId,
        public string $jobKey,
    ) {}

    public function handle(DocxParserService $parser): void
    {
        try {
            Cache::put(
                'import_job_' . $this->jobKey,
                ['status' => 'processing', 'progress' => 'Generating page images...'],
                now()->addMinutes(10)
            );

            // Normalize path for cross-platform compatibility
            $filePath = str_replace('\\', '/', $this->filePath);

            Log::info("ParseDocumentJob: starting parse for job {$this->jobKey}");

            Log::info('Job file check', [
                'path' => $filePath,
                'exists' => file_exists($filePath),
                'original' => $this->filePath,
            ]);

            $parsed = $parser->parse($filePath);

            // Strip base64 images from HTML — too large for cache
            $parsed['html'] = preg_replace(
                '/src="data:[^"]+"/i',
                'src=""',
                $parsed['html']
            );

            Cache::put(
                'import_job_' . $this->jobKey,
                [
                    'status' => 'complete',
                    'html' => $parsed['html'],
                    'fields' => $parsed['fields'],
                    'template_name' => $this->templateName,
                    'original_filename' => $this->originalFilename,
                ],
                now()->addMinutes(30)
            );

            Log::info("ParseDocumentJob: completed for job {$this->jobKey}, fields: " . count($parsed['fields']));

            // Clean up temp file
            if (file_exists($this->filePath)) {
                @unlink($this->filePath);
            }
        } catch (\Exception $e) {
            Log::error("ParseDocumentJob: failed for job {$this->jobKey}: " . $e->getMessage());

            Cache::put(
                'import_job_' . $this->jobKey,
                [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ],
                now()->addMinutes(10)
            );
        }
    }
}
