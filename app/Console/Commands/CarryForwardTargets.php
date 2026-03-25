<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CarryForwardTargets extends Command
{
    protected $signature = 'targets:carry-forward
                            {--month= : Target month (default: current)}
                            {--year= : Target year (default: current)}
                            {--force : Overwrite existing targets for the target month}';

    protected $description = 'Copy previous month targets to current month for all agents and branches';

    public function handle(): int
    {
        $targetMonth = (int) ($this->option('month') ?: now()->month);
        $targetYear  = (int) ($this->option('year') ?: now()->year);
        $force       = (bool) $this->option('force');

        $targetPeriod = sprintf('%04d-%02d', $targetYear, $targetMonth);

        $prevDate   = Carbon::create($targetYear, $targetMonth, 1)->subMonth();
        $prevPeriod = $prevDate->format('Y-m');

        $this->info("Carrying forward targets from {$prevPeriod} to {$targetPeriod}" . ($force ? ' (force mode)' : ''));

        $totals = [
            'targets'             => $this->carryTable('targets', 'user_id', $prevPeriod, $targetPeriod, $force),
            'monthly_target_goals' => $this->carryMonthlyGoals($prevPeriod, $targetPeriod, $force),
            'activity_targets'    => $this->carryTable('activity_targets', 'user_id', $prevPeriod, $targetPeriod, $force),
            'activity_point_goals' => $this->carryTable('activity_point_goals', 'user_id', $prevPeriod, $targetPeriod, $force),
            'listing_targets'     => $this->carryTable('listing_targets', 'user_id', $prevPeriod, $targetPeriod, $force),
        ];

        $this->newLine();
        $this->table(['Table', 'Created', 'Skipped', 'Overwritten'], collect($totals)->map(function ($t, $table) {
            return [$table, $t['created'], $t['skipped'], $t['overwritten']];
        })->values()->all());

        $totalCreated = collect($totals)->sum('created');
        if ($totalCreated === 0 && collect($totals)->sum('overwritten') === 0) {
            $this->warn('No new targets were carried forward. Either previous month had no data or current month already has all entries.');
        } else {
            $this->info('Done.');
        }

        return self::SUCCESS;
    }

    /**
     * Carry forward a standard per-user target table.
     * Unique key: period + user_id
     */
    private function carryTable(string $table, string $userKey, string $prevPeriod, string $targetPeriod, bool $force): array
    {
        $stats = ['created' => 0, 'skipped' => 0, 'overwritten' => 0];

        if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
            $this->warn("  Table '{$table}' does not exist — skipping.");
            return $stats;
        }

        $hasSoftDeletes = \Illuminate\Support\Facades\Schema::hasColumn($table, 'deleted_at');

        $prevRows = DB::table($table)
            ->where('period', $prevPeriod)
            ->when($hasSoftDeletes, fn ($q) => $q->whereNull('deleted_at'))
            ->get();

        if ($prevRows->isEmpty()) {
            $this->line("  {$table}: no rows for {$prevPeriod}");
            return $stats;
        }

        foreach ($prevRows as $row) {
            $data    = (array) $row;
            $userId  = $data[$userKey] ?? null;

            $existsQuery = DB::table($table)
                ->where('period', $targetPeriod)
                ->when($hasSoftDeletes, fn ($q) => $q->whereNull('deleted_at'));

            if ($userId !== null) {
                $existsQuery->where($userKey, $userId);
            }

            // Also match branch_id for rows that use it (like monthly_target_goals)
            if (isset($data['branch_id'])) {
                $existsQuery->where('branch_id', $data['branch_id']);
            }

            $exists = $existsQuery->exists();

            if ($exists && ! $force) {
                $stats['skipped']++;
                continue;
            }

            unset($data['id']);
            if ($hasSoftDeletes) {
                unset($data['deleted_at']);
            }
            $data['period']     = $targetPeriod;
            $data['created_at'] = now();
            $data['updated_at'] = now();

            if ($exists && $force) {
                $updateQuery = DB::table($table)
                    ->where('period', $targetPeriod)
                    ->when($hasSoftDeletes, fn ($q) => $q->whereNull('deleted_at'));

                if ($userId !== null) {
                    $updateQuery->where($userKey, $userId);
                }
                if (isset($data['branch_id'])) {
                    $updateQuery->where('branch_id', $data['branch_id']);
                }

                unset($data['period']); // don't update period in the WHERE match
                $updateQuery->update($data);
                $stats['overwritten']++;
            } else {
                DB::table($table)->insert($data);
                $stats['created']++;
            }
        }

        $this->line("  {$table}: {$stats['created']} created, {$stats['skipped']} skipped, {$stats['overwritten']} overwritten");
        return $stats;
    }

    /**
     * Carry forward monthly_target_goals (compound key: period + user_id + branch_id).
     */
    private function carryMonthlyGoals(string $prevPeriod, string $targetPeriod, bool $force): array
    {
        $stats = ['created' => 0, 'skipped' => 0, 'overwritten' => 0];
        $table = 'monthly_target_goals';

        if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
            $this->warn("  Table '{$table}' does not exist — skipping.");
            return $stats;
        }

        $prevRows = DB::table($table)
            ->where('period', $prevPeriod)
            ->whereNull('deleted_at')
            ->get();

        if ($prevRows->isEmpty()) {
            $this->line("  {$table}: no rows for {$prevPeriod}");
            return $stats;
        }

        foreach ($prevRows as $row) {
            $data = (array) $row;

            $existsQuery = DB::table($table)
                ->where('period', $targetPeriod)
                ->whereNull('deleted_at');

            // Match the exact scope: user_id (nullable) + branch_id (nullable)
            if ($data['user_id'] !== null) {
                $existsQuery->where('user_id', $data['user_id']);
            } else {
                $existsQuery->whereNull('user_id');
            }

            if ($data['branch_id'] !== null) {
                $existsQuery->where('branch_id', $data['branch_id']);
            } else {
                $existsQuery->whereNull('branch_id');
            }

            $exists = $existsQuery->exists();

            if ($exists && ! $force) {
                $stats['skipped']++;
                continue;
            }

            unset($data['id'], $data['deleted_at']);
            $data['period']     = $targetPeriod;
            $data['created_at'] = now();
            $data['updated_at'] = now();

            if ($exists && $force) {
                $updateQuery = DB::table($table)
                    ->where('period', $targetPeriod)
                    ->whereNull('deleted_at');

                if ($row->user_id !== null) {
                    $updateQuery->where('user_id', $row->user_id);
                } else {
                    $updateQuery->whereNull('user_id');
                }

                if ($row->branch_id !== null) {
                    $updateQuery->where('branch_id', $row->branch_id);
                } else {
                    $updateQuery->whereNull('branch_id');
                }

                unset($data['period']);
                $updateQuery->update($data);
                $stats['overwritten']++;
            } else {
                DB::table($table)->insert($data);
                $stats['created']++;
            }
        }

        $this->line("  {$table}: {$stats['created']} created, {$stats['skipped']} skipped, {$stats['overwritten']} overwritten");
        return $stats;
    }
}
