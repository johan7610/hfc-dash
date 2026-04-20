<?php

namespace App\Console\Commands;

use App\Models\P24ImportRow;
use App\Models\P24ImportRun;
use App\Models\P24OnboardingPortal;
use Illuminate\Console\Command;

class InspectP24Portal extends Command
{
    protected $signature = 'inspect:p24-portal
                            {slug? : Portal slug (defaults to latest portal)}
                            {--external= : External listing ID to look up (e.g. 100314527)}';

    protected $description = 'Dump the state of a P24 onboarding portal, its scope, and sample rows.';

    public function handle(): int
    {
        $slug = $this->argument('slug');
        $portal = $slug
            ? P24OnboardingPortal::where('slug', $slug)->orWhere('token', $slug)->first()
            : P24OnboardingPortal::orderByDesc('id')->first();

        if (!$portal) {
            $this->error('No portal found.');
            return self::FAILURE;
        }

        $this->line("<fg=cyan>Portal</>");
        $this->line("  id:         {$portal->id}");
        $this->line("  agency_id:  {$portal->agency_id}");
        $this->line("  slug:       " . ($portal->slug ?? '<null>'));
        $this->line("  token:      " . substr($portal->token, 0, 10) . '...');
        $this->line("  label:      " . ($portal->label ?? '<null>'));
        $this->line("  run_ids_json: " . json_encode($portal->run_ids_json));
        $this->line("  expires_at: " . ($portal->expires_at ?? '<null>'));
        $this->line("  revoked_at: " . ($portal->revoked_at ?? '<null>'));

        $this->newLine();
        $this->line("<fg=cyan>Import runs for agency {$portal->agency_id}</>");
        $runs = P24ImportRun::withTrashed()
            ->where('agency_id', $portal->agency_id)
            ->where('kind', 'listings_images')
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'status', 'created_at', 'deleted_at']);
        foreach ($runs as $r) {
            $rowCount = P24ImportRow::withTrashed()->where('run_id', $r->id)->count();
            $trash = $r->trashed() ? ' <fg=red>[trashed]</>' : '';
            $this->line("  run #{$r->id}  status={$r->status}  rows={$rowCount}  {$r->created_at}{$trash}");
        }

        $this->newLine();
        $count = $portal->rowsQuery()->count();
        $this->line("<fg=cyan>Rows visible to \$portal->rowsQuery(): {$count}</>");
        $portal->rowsQuery()
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'external_id', 'status', 'run_id'])
            ->each(function ($r) {
                $this->line("  row.id={$r->id}  external_id={$r->external_id}  status={$r->status}  run_id={$r->run_id}");
            });

        if ($external = $this->option('external')) {
            $this->newLine();
            $this->line("<fg=cyan>Lookup for external_id={$external}</>");
            $rows = P24ImportRow::withTrashed()->where('external_id', $external)->get(['id','run_id','status','row_type','deleted_at']);
            if ($rows->isEmpty()) {
                $this->warn('  No row with that external_id exists.');
            }
            foreach ($rows as $r) {
                $this->line("  row.id={$r->id}  run_id={$r->run_id}  status={$r->status}  type={$r->row_type}  deleted_at={$r->deleted_at}");
            }
        }

        return self::SUCCESS;
    }
}
