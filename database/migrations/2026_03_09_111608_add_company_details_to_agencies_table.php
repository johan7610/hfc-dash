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
            $table->string('trading_name')->nullable()->after('name');
            $table->string('tagline')->nullable()->after('trading_name');
            $table->string('address')->nullable()->after('tagline');
            $table->string('phone')->nullable()->after('address');
            $table->string('fax')->nullable()->after('phone');
            $table->string('email')->nullable()->after('fax');
            $table->string('reg_no')->nullable()->after('email');
            $table->string('vat_no')->nullable()->after('reg_no');
            $table->string('ffc_no')->nullable()->after('vat_no');
            $table->string('fic_no')->nullable()->after('ffc_no');
        });

        // Seed HFC Coastal with its actual details
        DB::table('agencies')->where('slug', 'hfc-coastal')->update([
            'trading_name' => 'Johan and Elize Properties T/A',
            'tagline'      => 'THE MANDATE COMPANY',
            'address'      => 'Shop 5 The Emporium, cnr King Rd & Marine Drive, Shelly Beach',
            'phone'        => '079 495 5994',
            'fax'          => '086 233 2395',
            'email'        => 'info@hfcoastal.co.za',
            'reg_no'       => '2009/228978/23',
            'vat_no'       => '4870264498',
            'ffc_no'       => 'FFC40/43916/5',
            'fic_no'       => '58538',
        ]);
    }

    public function down(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->dropColumn([
                'trading_name', 'tagline', 'address', 'phone', 'fax',
                'email', 'reg_no', 'vat_no', 'ffc_no', 'fic_no',
            ]);
        });
    }
};
