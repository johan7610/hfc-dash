<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('signature_templates')) {
            return;
        }

        Schema::table('signature_templates', function (Blueprint $table) {
            if (!Schema::hasColumn('signature_templates', 'supersedes_id')) {
                $table->unsignedBigInteger('supersedes_id')->nullable()->after('cosign_mode');
            }
            if (!Schema::hasColumn('signature_templates', 'superseded_by_id')) {
                $table->unsignedBigInteger('superseded_by_id')->nullable()->after('supersedes_id');
            }
        });

        // Add foreign keys separately (SQLite doesn't support adding FKs to existing tables)
        if (config('database.default') !== 'sqlite') {
            Schema::table('signature_templates', function (Blueprint $table) {
                $table->foreign('supersedes_id')->references('id')->on('signature_templates')->nullOnDelete();
                $table->foreign('superseded_by_id')->references('id')->on('signature_templates')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('signature_templates')) {
            return;
        }

        Schema::table('signature_templates', function (Blueprint $table) {
            if (config('database.default') !== 'sqlite') {
                $table->dropForeign(['supersedes_id']);
                $table->dropForeign(['superseded_by_id']);
            }
            $table->dropColumn(['supersedes_id', 'superseded_by_id']);
        });
    }
};
