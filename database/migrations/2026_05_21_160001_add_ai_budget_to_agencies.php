<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MIC Phase B2 — per-agency AI monthly budget cap.
 *
 * Spec wording uses filename `2026_05_21_140001_add_ai_budget_to_agencies`
 * but `_140001_` was already taken by the Phase A3 follow-up
 * (relax_agent_activity_events_user_id_nullable). Using `_160001_` to
 * avoid the clash; semantically identical.
 *
 * Defaults: R1,000/month, warn at 80%, hard-stop at 110% (safety overshoot
 * window). overage_allowed=false by default; super-admin may toggle on per
 * agency. Audit timestamps (last_warned_at / last_hard_stopped_at) prevent
 * notification spam — one warning per threshold per month.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->decimal('ai_monthly_budget_zar', 10, 2)->default(1000.00)->after('whatsapp_launch_mode_seller');
            $table->unsignedTinyInteger('ai_budget_warning_pct')->default(80)->after('ai_monthly_budget_zar');
            $table->unsignedTinyInteger('ai_budget_hard_cap_pct')->default(110)->after('ai_budget_warning_pct');
            $table->boolean('ai_budget_overage_allowed')->default(false)->after('ai_budget_hard_cap_pct');
            $table->timestamp('ai_budget_last_warned_at')->nullable()->after('ai_budget_overage_allowed');
            $table->timestamp('ai_budget_last_hard_stopped_at')->nullable()->after('ai_budget_last_warned_at');
        });
    }

    public function down(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->dropColumn([
                'ai_monthly_budget_zar',
                'ai_budget_warning_pct',
                'ai_budget_hard_cap_pct',
                'ai_budget_overage_allowed',
                'ai_budget_last_warned_at',
                'ai_budget_last_hard_stopped_at',
            ]);
        });
    }
};
