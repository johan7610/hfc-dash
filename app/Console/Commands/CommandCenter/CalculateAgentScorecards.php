<?php

namespace App\Console\Commands\CommandCenter;

use App\Services\CommandCenter\AgentScorecardCalculator;
use Illuminate\Console\Command;

class CalculateAgentScorecards extends Command
{
    protected $signature = 'command-center:scorecards {--type=weekly : Period type (weekly or monthly)}';
    protected $description = 'Calculate agent scorecards for the current period';

    public function handle(): int
    {
        $type = $this->option('type');
        $this->info("Calculating {$type} agent scorecards...");

        $calculator = new AgentScorecardCalculator();
        $count = $calculator->calculateAllWeekly();

        $this->info("Done. Calculated scorecards for {$count} agents.");

        return self::SUCCESS;
    }
}
