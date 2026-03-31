<?php

namespace App\Console\Commands\CommandCenter;

use App\Services\CommandCenter\AutoEventService;
use Illuminate\Console\Command;

class BackfillCalendarEvents extends Command
{
    protected $signature = 'command-center:backfill';
    protected $description = 'Generate calendar events from existing properties and deals (one-time)';

    public function handle(): int
    {
        $this->info('Backfilling calendar events from existing data...');

        $service = new AutoEventService();
        $count = $service->backfillProperties();

        $this->info("Done. Created {$count} events from existing properties.");

        return self::SUCCESS;
    }
}
