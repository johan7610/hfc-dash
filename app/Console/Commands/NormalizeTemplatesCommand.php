<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Docuperfect\Template;
use App\Services\Docuperfect\RoleBlockNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/**
 * One-time backfill — apply the data-role-block contract to every
 * existing CDS template in the local database. After this command
 * runs, every template carries the structural attributes the
 * contract-driven RoleBlockExpansionService expects.
 *
 * Going forward, every NEW imported template gets the contract
 * stamped at import-time via the cdsGenerate hook — this command
 * is only needed once to bring existing templates into compliance.
 *
 * Usage:
 *   php artisan docuperfect:normalize-templates              (apply)
 *   php artisan docuperfect:normalize-templates --dry-run    (preview)
 *   php artisan docuperfect:normalize-templates --id=111     (single template)
 *
 * Each pass:
 *   1. Normalises `editor_state.tagged_html` (stamps data-role-block on
 *      block ancestors of role-fields).
 *   2. Regenerates the blade view file so the rendered HTML at signing
 *      time carries the contract too.
 *   3. Clears the compiled-view cache so the next render reads the
 *      fresh blade.
 *
 * Idempotent: running twice on the same template produces no change.
 */
final class NormalizeTemplatesCommand extends Command
{
    protected $signature = 'docuperfect:normalize-templates'
        . ' {--dry-run : Preview the normalisation without writing}'
        . ' {--id= : Limit to a single template id}';

    protected $description = 'Apply the data-role-block contract to existing CDS templates (one-time backfill)';

    public function handle(RoleBlockNormalizer $normalizer): int
    {
        $query = Template::query()->where('template_type', 'cds');
        if ($this->option('id')) {
            $query->where('id', (int) $this->option('id'));
        }
        $templates = $query->get();

        $this->info('Found ' . $templates->count() . ' CDS template(s) to inspect');
        if ($this->option('dry-run')) {
            $this->warn('DRY-RUN — no writes will occur.');
        }

        $changed = 0;
        $skipped = 0;
        foreach ($templates as $template) {
            $editorState = $template->editor_state ?? [];
            $taggedHtml = $editorState['tagged_html'] ?? null;
            if (!is_string($taggedHtml) || trim($taggedHtml) === '') {
                $this->line("  · {$template->id} ({$template->name}) — no tagged_html, skipped");
                $skipped++;
                continue;
            }

            $normalised = $normalizer->normalize($taggedHtml);
            if ($normalised === $taggedHtml) {
                $this->line("  ✓ {$template->id} ({$template->name}) — already normalised");
                $skipped++;
                continue;
            }

            $this->line("  ✎ {$template->id} ({$template->name}) — applying contract");
            if ($this->option('dry-run')) {
                $changed++;
                continue;
            }

            // Write back to editor_state and the legacy tagged_html (some
            // templates keep both).
            $editorState['tagged_html'] = $normalised;
            $template->editor_state = $editorState;
            $template->save();

            // Re-stamp the rendered blade so the contract reaches the
            // signing-time HTML. The blade-file path is `blade_view`
            // (e.g. `docuperfect.web-templates.cds.template-111`), which
            // resolves to `resources/views/docuperfect/web-templates/cds/
            // template-111.blade.php`. We do an in-place normalise of
            // the existing blade-file content — same input → same
            // output as the editor-state pass.
            if (!empty($template->blade_view)) {
                $bladePath = resource_path('views/' . str_replace('.', '/', $template->blade_view) . '.blade.php');
                if (File::exists($bladePath)) {
                    $bladeContent = File::get($bladePath);
                    $normalisedBlade = $normalizer->normalize($bladeContent);
                    if ($normalisedBlade !== $bladeContent) {
                        File::put($bladePath, $normalisedBlade);
                    }
                }
            }

            $changed++;
        }

        if (!$this->option('dry-run')) {
            Artisan::call('view:clear');
        }

        $this->newLine();
        $this->info("Done — {$changed} normalised, {$skipped} unchanged");
        return self::SUCCESS;
    }
}
