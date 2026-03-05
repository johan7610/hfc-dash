<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'property_ad_templates',
        'presentation_links',
        'presentation_articles',
        'presentation_uploads',
        'portal_captures',
        'designations',
        'p24_suburbs',
        'splitter_doc_types',
        'knowledge_documents',
        'knowledge_categories',
        'commercial_evaluation_comparables',
        'commercial_evaluation_assets',
        'commercial_evaluation_units',
        'commercial_evaluation_crops',
        'commercial_evaluation_livestock',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropSoftDeletes();
            });
        }
    }
};
