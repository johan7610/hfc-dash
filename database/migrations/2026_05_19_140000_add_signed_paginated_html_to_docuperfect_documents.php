<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §19 Option-2 fix. merged_html (in docuperfect_documents.web_template_data)
 * is the CANONICAL, un-paginated document — the only thing the signing view
 * loads, so the client paginator always gets clean input and the
 * re-pagination accretion loop is structurally impossible.
 *
 * This nullable column holds the EXACT client-paginated, signed DOM written
 * ONCE on completion. It is consumed ONLY by SignatureService::
 * splitMergedHtml() and SignaturePdfService — never fed back to the client
 * paginator. Honours §19.7 ("PDF from the exact paginated DOM, no server
 * re-pagination") without corrupting the canonical source.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('docuperfect_documents', function (Blueprint $table) {
            if (! Schema::hasColumn('docuperfect_documents', 'signed_paginated_html')) {
                $table->longText('signed_paginated_html')->nullable()->after('web_template_data');
            }
        });
    }

    public function down(): void
    {
        Schema::table('docuperfect_documents', function (Blueprint $table) {
            if (Schema::hasColumn('docuperfect_documents', 'signed_paginated_html')) {
                $table->dropColumn('signed_paginated_html');
            }
        });
    }
};
