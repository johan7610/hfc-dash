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

        // Demo work ALWAYS targets the dedicated 'demo' connection
        // (nexus_os_demo), NEVER the real working DB. Pre-flight: refuse if
        // the demo connection itself resolves to a protected real DB.
        $demoDb = config('database.connections.demo.database');
        if ($refusal = DemoDataSeeder::protectedDatabaseRefusal($demoDb)) {
            $this->error($refusal);
            return self::FAILURE;
        }

        $this->info("Seeding demo data into the 'demo' connection ({$demoDb})...");
        // --database=demo: db:seed switches the default connection for the
        // run, so the seeder writes to nexus_os_demo. --force: required so
        // db:seed does not prompt/abort on non-local and so the seeder's own
        // run() gates see the operator's --force intent.
        $this->call('db:seed', [
            '--class' => 'Database\\Seeders\\DemoDataSeeder',
            '--database' => 'demo',
            '--force' => true,
        ]);

        return self::SUCCESS;
    }
}
