<?php

namespace App\Services\AI;

use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;

class KnowledgeSearchService
{
    private EmbeddingService $embeddingService;

    public function __construct(EmbeddingService $embeddingService)
    {
        $this->embeddingService = $embeddingService;
    }

    /**
     * Search knowledge base for relevant chunks using vector similarity.
     *
     * @return array{context: string, sources: array}
     */
    public function search(string $query, int $limit = 3): array
    {
        try {
            $queryEmbedding = $this->embeddingService->embed($query);

            if ($queryEmbedding === null) {
                return ['context' => '', 'sources' => []];
            }

            // Load all embedded chunks from active, ready, ellie-enabled documents
            $chunks = KnowledgeChunk::whereHas('document', function ($q) {
                $q->where('is_active', true)
                  ->where('status', 'ready')
                  ->where('is_ellie_enabled', true);
            })
                ->where('has_embedding', true)
                ->with('document')
                ->get();

            if ($chunks->isEmpty()) {
                return ['context' => '', 'sources' => []];
            }

            // Score each chunk by cosine similarity
            $scored = $chunks->map(function ($chunk) use ($queryEmbedding) {
                $similarity = $this->embeddingService->cosineSimilarity(
                    $queryEmbedding,
                    $chunk->embedding
                );
                return ['chunk' => $chunk, 'score' => $similarity];
            });

            // Sort by similarity descending, take top N
            $topChunks = $scored->sortByDesc('score')->take($limit);

            $contextParts = [];
            $sources = [];

            foreach ($topChunks as $item) {
                $chunk = $item['chunk'];
                $doc = $chunk->document;
                $header = "--- From: {$doc->title}";
                if ($chunk->section_title) {
                    $header .= " ({$chunk->section_title})";
                }
                if ($chunk->page_number) {
                    $header .= " [Page {$chunk->page_number}]";
                }
                $header .= " ---";

                $contextParts[] = $header . "\n" . $chunk->content;

                $sources[] = [
                    'document_id' => $doc->id,
                    'title' => $doc->title,
                    'section' => $chunk->section_title,
                    'page' => $chunk->page_number,
                    'category' => $doc->category->name ?? null,
                ];
            }

            return [
                'context' => implode("\n\n", $contextParts),
                'sources' => $sources,
            ];
        } catch (\Throwable $e) {
            \Log::warning('Knowledge search failed: ' . $e->getMessage());
            return ['context' => '', 'sources' => []];
        }
    }

    /**
     * Determine if the message warrants a knowledge base search.
     */
    public function shouldSearch(string $message): bool
    {
        // Always search when KB documents with embeddings are available
        if (KnowledgeDocument::where('status', 'ready')->where('is_ellie_enabled', true)->exists()) {
            return true;
        }

        // Fallback: keyword gate for when no ready documents exist (avoids unnecessary queries)
        $lower = mb_strtolower($message);

        $patterns = [
            'what does', 'what is', 'what are',
            'clause', 'section', 'policy', 'procedure',
            'otp', 'mandate', 'fica', 'compliance',
            'commission', 'split', 'transfer', 'trust account',
            'lease', 'rental', 'tell me about', 'explain',
            'according to', 'cpd', 'ppra', 'eaab',
            'conveyancing', 'bond', 'contract', 'agreement',
            'regulation', 'rule', 'guideline', 'requirement',
            'training', 'onboarding', 'branding', 'marketing',
            'how do i', 'how does', 'what should',
            'knowledge base', 'document says',
            'evaluation', 'valuation', 'commercial', 'agricultural',
            'hospitality', 'industrial', 'crop', 'livestock',
            'comparable', 'financial', 'calculator', 'bond overpayment',
            'fee scale', 'knowledge', 'document',
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($lower, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
