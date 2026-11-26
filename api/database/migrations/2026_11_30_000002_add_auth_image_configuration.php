<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('configurations')->insertOrIgnore([
            'key' => 'auth_image',
            'title' => 'Sign-in Image',
            'value' => null,
            'type' => 'file',
            'count' => null,
            'max' => null,
            'col' => 12,
            'autogrow' => false,
            'hint' => 'Shown beside the sign-in, sign-up and invite forms. Leave empty to use the default.',
            'secret' => false,
            'group' => 'appearance',
            'choices' => json_encode([]),
        ]);

        Cache::forget('laravel-dbconfig.configurations::build');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('configurations')->where('key', 'auth_image')->delete();
    }
};
