<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->string('sidebar_color', 20)->default('#0ea5e9')->after('slug');
            $table->string('icon_color', 20)->default('#0ea5e9')->after('sidebar_color');
            $table->string('default_color', 20)->default('#0b2a4a')->after('icon_color');
            $table->string('button_color', 20)->default('#0ea5e9')->after('default_color');
        });

        // Migrate existing data: map old colours to new semantic roles
        DB::table('agencies')->get()->each(function ($agency) {
            DB::table('agencies')->where('id', $agency->id)->update([
                'sidebar_color' => $agency->secondary_color ?? '#0ea5e9',
                'icon_color'    => $agency->secondary_color ?? '#0ea5e9',
                'default_color' => $agency->primary_color ?? '#0b2a4a',
                'button_color'  => $agency->secondary_color ?? '#0ea5e9',
            ]);
        });

        Schema::table('agencies', function (Blueprint $table) {
            $table->dropColumn(['primary_color', 'secondary_color', 'tertiary_color']);
        });
    }

    public function down(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->string('primary_color', 20)->default('#0b2a4a')->after('slug');
            $table->string('secondary_color', 20)->default('#00b4d8')->after('primary_color');
            $table->string('tertiary_color', 20)->default('#1a4a73')->after('secondary_color');
        });

        DB::table('agencies')->get()->each(function ($agency) {
            DB::table('agencies')->where('id', $agency->id)->update([
                'primary_color'   => $agency->sidebar_color ?? '#0b2a4a',
                'secondary_color' => $agency->icon_color ?? '#00b4d8',
                'tertiary_color'  => $agency->default_color ?? '#1a4a73',
            ]);
        });

        Schema::table('agencies', function (Blueprint $table) {
            $table->dropColumn(['sidebar_color', 'icon_color', 'default_color', 'button_color']);
        });
    }
};
