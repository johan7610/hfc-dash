<?php

namespace App\Console\Commands;

use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DemoCleanup extends Command
{
    protected $signature = 'demo:cleanup {--force : Skip confirmation; also REQUIRED (with DEMO_SEED_ALLOWED=true in .env) to run on a non-local environment}';
    protected $description = 'Remove all demo-prefixed data seeded by demo:seed. Local: runs directly. Non-local: requires --force AND DEMO_SEED_ALLOWED=true in that environment\'s .env (double-lock; a real production box can never be demo-cleaned).';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        if ($refusal = DemoDataSeeder::environmentGateRefusal($force)) {
            $this->error($refusal);
            return self::FAILURE;
        }

        // Cleanup ALWAYS operates on the dedicated 'demo' connection
        // (nexus_os_demo), NEVER the real working DB. Switch the default
        // connection so every DB::table() delete below is scoped to demo,
        // then hard-refuse if that connection resolves to a protected DB.
        DB::setDefaultConnection('demo');
        $demoDb = DB::connection()->getDatabaseName();
        if ($refusal = DemoDataSeeder::protectedDatabaseRefusal($demoDb)) {
            $this->error($refusal);
            return self::FAILURE;
        }
        $this->info("Target: 'demo' connection ({$demoDb})");

        if (!$force && !$this->confirm('This will delete ALL [DEMO]-prefixed records. Continue?')) {
            return self::SUCCESS;
        }

        $this->info('Cleaning up demo data...');

        // Get demo contact IDs first (needed for cascade)
        $demoContactIds = DB::table('contacts')
            ->where('first_name', 'like', '[DEMO]%')
            ->pluck('id')->toArray();

        // Get demo property IDs
        $demoPropertyIds = DB::table('properties')
            ->where('address', 'like', '[DEMO]%')
            ->pluck('id')->toArray();

        // Get demo event IDs
        $demoEventIds = DB::table('calendar_events')
            ->where('title', 'like', '[DEMO]%')
            ->pluck('id')->toArray();

        // Cascade: buyer data
        if (!empty($demoContactIds)) {
            // buyer_preferences cleanup removed — table is being deprecated (spec D11 Phase 1).
            // ContactMatch rows for demo contacts get deleted below as part of the cascade,
            // since contact_matches has a contact_id FK with cascadeOnDelete.
            $c = DB::table('contact_matches')->whereIn('contact_id', $demoContactIds)->delete();
            $this->line("  contact_matches: {$c}");
            $c = DB::table('buyer_property_responses')->whereIn('contact_id', $demoContactIds)->delete();
            $this->line("  buyer_property_responses: {$c}");
            $c = DB::table('buyer_activity_log')->whereIn('contact_id', $demoContactIds)->delete();
            $this->line("  buyer_activity_log: {$c}");
            $c = DB::table('buyer_lost_records')->whereIn('contact_id', $demoContactIds)->delete();
            $this->line("  buyer_lost_records: {$c}");
            $c = DB::table('buyer_lost_risk_scores')->whereIn('contact_id', $demoContactIds)->delete();
            $this->line("  buyer_lost_risk_scores: {$c}");
            $c = DB::table('prospecting_buyer_matches')->whereIn('contact_id', $demoContactIds)->delete();
            $this->line("  prospecting_buyer_matches (by contact): {$c}");
        }

        // Cascade: property data
        if (!empty($demoPropertyIds)) {
            $c = DB::table('property_sold_records')->whereIn('property_id', $demoPropertyIds)->delete();
            $this->line("  property_sold_records: {$c}");
            $c = DB::table('property_marketing_activities')->whereIn('property_id', $demoPropertyIds)->delete();
            $this->line("  property_marketing_activities: {$c}");
            $c = DB::table('property_buyer_matches')->whereIn('property_id', $demoPropertyIds)->delete();
            $this->line("  property_buyer_matches: {$c}");
        }

        // Cascade: calendar data
        if (!empty($demoEventIds)) {
            $c = DB::table('calendar_event_feedback')->whereIn('calendar_event_id', $demoEventIds)->delete();
            $this->line("  calendar_event_feedback: {$c}");
            $c = DB::table('calendar_event_links')->whereIn('calendar_event_id', $demoEventIds)->delete();
            $this->line("  calendar_event_links: {$c}");
            $c = DB::table('calendar_event_invitations')->whereIn('event_id', $demoEventIds)->delete();
            $this->line("  calendar_event_invitations: {$c}");
        }

        // Delete main records
        $c = DB::table('contacts')->where('first_name', 'like', '[DEMO]%')->delete();
        $this->line("  contacts: {$c}");

        $c = DB::table('properties')->where('address', 'like', '[DEMO]%')->delete();
        $this->line("  properties: {$c}");

        $c = DB::table('calendar_events')->where('title', 'like', '[DEMO]%')->delete();
        $this->line("  calendar_events: {$c}");

        // Clean notifications from observer
        $c = DB::table('notifications')->where('type', 'prospecting_match_alert')->delete();
        $this->line("  prospecting_match_alert notifications: {$c}");

        $this->info('Demo cleanup complete.');
        return self::SUCCESS;
    }
}
