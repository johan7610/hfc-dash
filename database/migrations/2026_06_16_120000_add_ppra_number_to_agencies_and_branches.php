<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 9c-1 — PPRA registration number per agency + per branch.
 *
 * Audit (.ai/audits/popia-columns-investigation-2026-05-25.md) confirmed that
 * under PPA 22/2019 the FFC (per-practitioner annual licence) is legally
 * distinct from the PPRA registration number (per-agency permanent ID).
 * `agencies.ffc_no` and `branches.ffc_no` already exist for the certificate
 * number; this migration adds the missing PPRA registration number column.
 *
 * Per-branch column included because HFC has separate PPRA registrations for
 * head office vs Southbroom — Johan confirmed the per-branch case.
 *
 * Population: not seeded in this migration. Populate via Tinker after
 * deploy with the real PPRA numbers per agency / branch. Until populated
 * the document/email footers will render `[PPRA not configured]` so the
 * gap is visible during compliance checks.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $t) {
            $t->string('ppra_number', 32)->nullable()->after('ffc_no');
        });
        Schema::table('branches', function (Blueprint $t) {
            $t->string('ppra_number', 32)->nullable()->after('ffc_no');
        });
    }

    public function down(): void
    {
        // TODO if rolling back in prod: capture populated values from agencies.ppra_number
        // and branches.ppra_number before drop — these are regulatory identifiers and
        // re-discovering them means contacting PPRA + each branch admin.
        Schema::table('agencies', function (Blueprint $t) {
            $t->dropColumn('ppra_number');
        });
        Schema::table('branches', function (Blueprint $t) {
            $t->dropColumn('ppra_number');
        });
    }
};
