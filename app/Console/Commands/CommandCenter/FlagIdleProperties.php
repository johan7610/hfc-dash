<?php

namespace App\Console\Commands\CommandCenter;

use App\Services\CommandCenter\AutoEventService;
use Illuminate\Console\Command;

class FlagIdleProperties extends Command
{
    protected $signature = 'command-center:flag-idle {--threshold=14 : Days before flagging} {--critical=30 : Days before critical}';
    protected $description = 'Create tasks for properties with no recent activity';

    public function handle(): int
    {
        $threshold = (int) $this->option('threshold');
        $critical  = (int) $this->option('critical');

        $this->info("Scanning for properties idle > {$threshold} days (critical at {$critical} days)...");

        $service = new AutoEventService();
        $result  = $service->flagIdleProperties($threshold, $critical);

        $this->info("Done. Flagged {$result['flagged']} properties ({$result['critical']} critical).");

        return self::SUCCESS;
    }
}
