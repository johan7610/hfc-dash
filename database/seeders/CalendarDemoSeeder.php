<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * CalendarDemoSeeder
 *
 * Seeds realistic manual calendar events for HFC across all branches
 * and agents. Tagged with metadata.demo = true for clean wipe.
 *
 * Idempotent: clears all source_type='manual:demo' rows before re-seeding.
 *
 * NOTE: This seeder predates M2.2 (calendar_event_links pivot) and
 * M2.4 (calendar_event_feedback table). Property/contact links use the
 * existing property_id/contact_id FK columns on calendar_events AND
 * additional linked IDs in metadata JSON. Feedback content is stored in
 * metadata JSON. When M2.2/M2.4 land, a migration will move the extra
 * metadata links to proper relations.
 *
 * Run: php artisan db:seed --class=CalendarDemoSeeder
 */
class CalendarDemoSeeder extends Seeder
{
    private const AGENCY_ID = 1;
    private const TARGET_EVENT_COUNT = 60;

    private const EVENT_PROFILES = [
        'viewing' => [
            'title_template'    => 'Viewing — :address',
            'time_window'       => [9, 17],
            'duration_min'      => 60,
            'weight'            => 50,
            'requires_property' => true,
            'requires_contact'  => true,
        ],
        'valuation' => [
            'title_template'    => 'Valuation — :address',
            'time_window'       => [10, 15],
            'duration_min'      => 90,
            'weight'            => 25,
            'requires_property' => true,
            'requires_contact'  => true,
        ],
        'listing_presentation' => [
            'title_template'    => 'Listing presentation — :contact',
            'time_window'       => [11, 14],
            'duration_min'      => 90,
            'weight'            => 25,
            'requires_property' => false,
            'requires_contact'  => true,
        ],
    ];

    private const FEEDBACK_SAMPLES = [
        ['outcome' => 'Interested', 'concerns' => ['Price'], 'seller_visible' => 'Buyer liked the layout, asked about the school zone.', 'internal' => 'Will follow up with bond pre-approval check.'],
        ['outcome' => 'Interested', 'concerns' => [], 'seller_visible' => 'Buyer loved the property and wants to make an offer.', 'internal' => 'Requesting OTP draft.'],
        ['outcome' => 'Not interested', 'concerns' => ['Damp/Maintenance'], 'seller_visible' => 'Concern raised about damp in the garage.', 'internal' => 'Recommend seller addresses damp before next viewing.'],
        ['outcome' => 'Not interested', 'concerns' => ['Layout'], 'seller_visible' => 'Open-plan layout did not suit the buyer.', 'internal' => 'Buyer wants single-storey traditional layout.'],
        ['outcome' => 'Not interested', 'concerns' => ['Location'], 'seller_visible' => 'Buyer felt the location was not right for them.', 'internal' => 'Too far from school. Move to north side suburbs.'],
        ['outcome' => 'No-show', 'concerns' => [], 'seller_visible' => 'Viewing did not take place — buyer did not arrive.', 'internal' => 'Buyer cancelled last minute. Will re-engage.'],
        ['outcome' => 'Made offer', 'concerns' => ['Price'], 'seller_visible' => 'Buyer made an offer below asking — under negotiation.', 'internal' => 'Offer received — discussing counter with seller.'],
        ['outcome' => 'Interested', 'concerns' => ['Size'], 'seller_visible' => 'Buyer would prefer something slightly larger but interested.', 'internal' => 'Show larger stock in same suburb.'],
    ];

    public function run(): void
    {
        $this->command->info('Wiping existing demo events...');
        $deleted = DB::table('calendar_events')->where('source_type', 'manual:demo')->delete();
        $this->command->info("Deleted {$deleted} demo events.");

        $branches = DB::table('branches')
            ->where('agency_id', self::AGENCY_ID)
            ->whereNull('deleted_at')
            ->get();

        $agents = DB::table('users')
            ->where('agency_id', self::AGENCY_ID)
            ->whereNull('deleted_at')
            ->whereNotNull('branch_id')
            ->get()
            ->groupBy('branch_id');

        $properties = DB::table('properties')
            ->where('agency_id', self::AGENCY_ID)
            ->whereNull('deleted_at')
            ->whereNotNull('agent_id')
            ->get();

        $contacts = DB::table('contacts')
            ->where('agency_id', self::AGENCY_ID)
            ->whereNull('deleted_at')
            ->get();

        if ($agents->isEmpty()) {
            $this->command->warn('No agents found. Aborting.');
            return;
        }

        if ($contacts->isEmpty()) {
            $this->command->warn('No contacts found. Aborting.');
            return;
        }

        $created = 0;
        $iterations = 0;

        while ($created < self::TARGET_EVENT_COUNT && $iterations < self::TARGET_EVENT_COUNT * 3) {
            $iterations++;

            $branch = $branches->random();
            $branchAgents = $agents->get($branch->id);

            if (!$branchAgents || $branchAgents->isEmpty()) {
                continue;
            }

            $category = $this->pickWeightedCategory();
            $profile = self::EVENT_PROFILES[$category];

            if ($profile['requires_property'] && $properties->isEmpty()) {
                continue;
            }

            $agent = $branchAgents->random();
            $contact = $contacts->random();
            $property = $profile['requires_property'] ? $properties->random() : null;

            [$eventDate, $isPast, $hasFeedback] = $this->pickEventDate($profile['time_window']);

            $title = $this->buildTitle($profile, $property, $contact);

            $metadata = [
                'demo' => true,
                'demo_seeded_at' => now()->toIso8601String(),
            ];

            if ($property) {
                $metadata['linked_property_id'] = $property->id;
            }
            $metadata['linked_contact_ids'] = [$contact->id];

            if ($isPast && $hasFeedback) {
                $fb = self::FEEDBACK_SAMPLES[array_rand(self::FEEDBACK_SAMPLES)];
                $metadata['feedback'] = [
                    'outcome'        => $fb['outcome'],
                    'concerns'       => $fb['concerns'],
                    'seller_visible' => $fb['seller_visible'],
                    'internal'       => $fb['internal'],
                    'captured_at'    => $eventDate->copy()->addHours(2)->toIso8601String(),
                    'captured_by'    => $agent->id,
                ];
            }

            $status = 'scheduled';
            if ($isPast && $hasFeedback) {
                $status = 'completed';
            } elseif ($isPast) {
                $status = 'pending'; // missed feedback = still pending
            }

            DB::table('calendar_events')->insert([
                'event_type'  => 'manual',
                'category'    => $category,
                'title'       => $title,
                'description' => null,
                'event_date'  => $eventDate,
                'end_date'    => $eventDate->copy()->addMinutes($profile['duration_min']),
                'all_day'     => false,
                'priority'    => 'normal',
                'send_reminder' => true,
                'status'      => $status,
                'source_type' => 'manual:demo',
                'source_id'   => null,
                'user_id'     => $agent->id,
                'property_id' => $property?->id,
                'contact_id'  => $contact->id,
                'agency_id'   => self::AGENCY_ID,
                'branch_id'   => $branch->id,
                'metadata'    => json_encode($metadata),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            $created++;
        }

        $this->command->info("Seeded {$created} demo events across {$branches->count()} branches.");

        // Summary
        $byCategory = DB::table('calendar_events')
            ->where('source_type', 'manual:demo')
            ->whereNull('deleted_at')
            ->selectRaw('category, COUNT(*) as cnt')
            ->groupBy('category')
            ->get();

        foreach ($byCategory as $row) {
            $this->command->info("  {$row->category}: {$row->cnt}");
        }
    }

    private function pickWeightedCategory(): string
    {
        $r = mt_rand(1, 100);
        $cumulative = 0;
        foreach (self::EVENT_PROFILES as $cat => $profile) {
            $cumulative += $profile['weight'];
            if ($r <= $cumulative) {
                return $cat;
            }
        }
        return 'viewing';
    }

    private function pickEventDate(array $timeWindow): array
    {
        $r = mt_rand(1, 100);

        if ($r <= 30) {
            // Past WITH feedback
            $date = now()->subDays(mt_rand(2, 30));
            return [$this->setRealisticTime($date, $timeWindow), true, true];
        }

        if ($r <= 60) {
            // Past WITHOUT feedback (missed-feedback test data)
            $date = now()->subDays(mt_rand(1, 7));
            return [$this->setRealisticTime($date, $timeWindow), true, false];
        }

        // Future
        $date = now()->addDays(mt_rand(1, 21));
        return [$this->setRealisticTime($date, $timeWindow), false, false];
    }

    private function setRealisticTime(Carbon $date, array $hourRange): Carbon
    {
        $hour = mt_rand($hourRange[0], $hourRange[1]);
        $minute = [0, 15, 30, 45][mt_rand(0, 3)];
        return $date->copy()->setTime($hour, $minute, 0);
    }

    private function buildTitle(array $profile, $property, $contact): string
    {
        $title = $profile['title_template'];
        $address = $property->address ?? $property->suburb ?? 'property';
        if (empty(trim($address))) {
            $address = $property->suburb ?? 'property';
        }
        $contactName = trim(($contact->first_name ?? '') . ' ' . ($contact->last_name ?? '')) ?: 'contact';

        return str_replace([':address', ':contact'], [$address, $contactName], $title);
    }
}
