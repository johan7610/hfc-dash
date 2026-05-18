<?php

namespace App\Console\Commands;

use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Command;

class DemoSeed extends Command
{
    protected $signature = 'demo:seed {--force : Required to run on a non-local environment that has set DEMO_SEED_ALLOWED=true in its .env}';

    protected $description = 'Seed the coherent demo dataset. Local: runs directly. Non-local: requires --force AND DEMO_SEED_ALLOWED=true in that environment\'s .env (double-lock; a real production box can never be demo-seeded).';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        if ($refusal = DemoDataSeeder::environmentGateRefusal($force)) {
            $this->error($refusal);
            return self::FAILURE;
        }

        $this->info('Seeding demo data...');
        // Pass --force through to db:seed: required so db:seed does not
        // prompt/abort on a non-local environment, and so the seeder's own
        // run() gate sees the operator's --force intent.
        $this->call('db:seed', [
            '--class' => 'Database\\Seeders\\DemoDataSeeder',
            '--force' => true,
        ]);

        return self::SUCCESS;
    }
}
