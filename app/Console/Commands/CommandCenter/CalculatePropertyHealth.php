<?php

namespace App\Console\Commands\CommandCenter;

use App\Services\CommandCenter\PropertyHealthCalculator;
use Illuminate\Console\Command;

class CalculatePropertyHealth extends Command
{
    protected $signature = 'command-center:health';
    protected $description = 'Calculate health scores for all active properties';

    public function handle(): int
    {
        $this->info('Calculating property health scores...');

        $calculator = new PropertyHealthCalculator();
        $count = $calculator->calculateAll();

        $this->info("Done. Calculated health for {$count} properties.");

        return self::SUCCESS;
    }
}
