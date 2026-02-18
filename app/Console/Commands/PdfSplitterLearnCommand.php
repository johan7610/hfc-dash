<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PdfSplitterLearnCommand extends Command
{
    protected $signature = 'pdf-splitter:learn {--dry-run : Do not write changes; only show what would be enabled}';
    protected $description = 'Rebuild learned phrases for PDF splitter from feedback table';

    public function handle(): int
    {
        if (!Schema::hasTable('pdf_splitter_feedback') || !Schema::hasTable('pdf_splitter_learned_phrases')) {
            $this->error('Missing tables. Run migrations first.');
            return self::FAILURE;
        }

        $threshold = 5;

        // Pull all feedback rows where auto != final and we have a snippet
        $rows = DB::table('pdf_splitter_feedback')
            ->select(['final_label','snippet'])
            ->whereColumn('auto_label','!=','final_label')
            ->whereNotNull('snippet')
            ->get();

        $counts = []; // [bucket][phrase] => hits

        foreach ($rows as $r) {
            $bucket = (string)$r->final_label;
            $snip   = mb_strtolower((string)$r->snippet);
            $snip   = preg_replace('/\s+/', ' ', trim($snip));

            // Extract bigrams
            $words = preg_split('/[^a-z0-9]+/i', $snip, -1, PREG_SPLIT_NO_EMPTY);
            if (!$words || count($words) < 2) continue;

            for ($i=0; $i < count($words)-1; $i++) {
                $a = $words[$i];
                $b = $words[$i+1];
                if (strlen($a) < 3 || strlen($b) < 3) continue;
                if (ctype_digit($a) || ctype_digit($b)) continue;

                $phrase = $a . ' ' . $b;

                $counts[$bucket] ??= [];
                $counts[$bucket][$phrase] = ($counts[$bucket][$phrase] ?? 0) + 1;
            }
        }

        // Flatten + filter by threshold
        $flat = [];
        foreach ($counts as $bucket => $phrases) {
            foreach ($phrases as $phrase => $hits) {
                if ($hits >= $threshold) {
                    $flat[] = ['bucket'=>$bucket,'phrase'=>$phrase,'hits'=>$hits];
                }
            }
        }

        usort($flat, fn($x,$y) => $y['hits'] <=> $x['hits']);

        $this->info('Phrases meeting threshold (>= ' . $threshold . '): ' . count($flat));
        foreach (array_slice($flat, 0, 40) as $row) {
            $this->line("  [{$row['bucket']}] {$row['phrase']} ({$row['hits']})");
        }

        if ($this->option('dry-run')) {
            $this->warn('Dry-run: no DB changes written.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($flat) {
            DB::table('pdf_splitter_learned_phrases')->truncate();

            foreach ($flat as $row) {
                DB::table('pdf_splitter_learned_phrases')->insert([
                    'phrase'   => $row['phrase'],
                    'bucket'   => $row['bucket'],
                    'hits'     => $row['hits'],
                    'weight'   => 1,
                    'enabled'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        $this->info('Rebuilt learned phrases successfully.');
        return self::SUCCESS;
    }
}
