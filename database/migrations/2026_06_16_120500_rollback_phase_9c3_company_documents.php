<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rollback Phase 9c-3 — Company Documents over-build.
 *
 * Yesterday's `2026_06_16_120200_create_company_documents_table` created a
 * `company_documents` table + admin UI + `/legal/{token}` public route as
 * net-new infrastructure. The .ai/audits/documents-infrastructure-audit-2026-05-26.md
 * audit confirmed this duplicates the scope of the existing
 * `agency_compliance_provisions` system (branch-override-capable) without
 * gaining its key features. The minimal "editable privacy policy + public
 * URL" ask is being rebuilt as a Company Settings field next to Email
 * Disclaimer — see the immediately-following migration
 * `add_privacy_policy_fields_to_agencies_and_branches`.
 *
 * Phase 9c-3 only ever existed on `feature/map-workspace-overhaul`; the
 * create migration file is being deleted alongside this rollback. Other
 * local clones that ran the create migration will execute this drop on
 * pull and end up consistent.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('company_documents');
    }

    public function down(): void
    {
        // No-op. This rollback is one-way by design — the table is being
        // retired in favour of the field-based pattern. Re-creating it via
        // this migration's down() would resurrect the over-build.
    }
};
