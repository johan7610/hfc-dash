<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 9c-3 rebuild — privacy policy as a Company Settings field.
 *
 * Replaces the rolled-back `company_documents` table. Privacy policy
 * lives next to Email Disclaimer in Company Settings (agencies) with a
 * matching per-branch override that follows the existing override
 * convention (plain column names on branches, NULL means inherit).
 *
 * Naming convention note: the prompt suggested `*_override` suffixes for
 * the branch columns, but the existing override pattern in this codebase
 * does NOT use suffixes (see branches.ffc_no / branches.ppra_number /
 * branches.trading_name — all plain names that mirror agencies columns,
 * with NULL meaning inherit). Mirroring that convention wins over the
 * suggested suffix, per the prompt's own "mirror exactly" rule.
 *
 * Public URL: /legal/privacy/{token}. Token is set on first save AND
 * persists across edits — agents may share the link before publishing.
 * Token rotation is a separate future endpoint.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $t) {
            $t->longText('privacy_policy_markdown')->nullable()->after('popi_url');
            $t->string('privacy_policy_token', 64)->nullable()->unique()->after('privacy_policy_markdown');
            $t->timestamp('privacy_policy_published_at')->nullable()->after('privacy_policy_token');
        });

        Schema::table('branches', function (Blueprint $t) {
            // Plain column names mirroring the existing branch-override
            // convention (NULL = inherit from agency).
            $t->longText('privacy_policy_markdown')->nullable();
            $t->string('privacy_policy_token', 64)->nullable()->unique();
            $t->timestamp('privacy_policy_published_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $t) {
            $t->dropUnique(['privacy_policy_token']);
            $t->dropColumn(['privacy_policy_markdown', 'privacy_policy_token', 'privacy_policy_published_at']);
        });

        Schema::table('agencies', function (Blueprint $t) {
            $t->dropUnique(['privacy_policy_token']);
            $t->dropColumn(['privacy_policy_markdown', 'privacy_policy_token', 'privacy_policy_published_at']);
        });
    }
};
