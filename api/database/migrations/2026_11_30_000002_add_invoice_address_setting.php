<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Adds the invoice address setting to installs seeded before it existed, keeping any saved value.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('configurations')->insertOrIgnore([
            'key' => 'invoice_address',
            'title' => 'Invoice Address',
            'value' => '',
            'type' => 'text',
            'col' => 6,
            'autogrow' => false,
            'hint' => 'Business address printed on payment invoices.',
            'secret' => false,
            'group' => 'main',
            'choices' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cache::forget('laravel-dbconfig.configurations::build');
    }

    public function down(): void
    {
        DB::table('configurations')->where('key', 'invoice_address')->delete();

        Cache::forget('laravel-dbconfig.configurations::build');
    }
};
